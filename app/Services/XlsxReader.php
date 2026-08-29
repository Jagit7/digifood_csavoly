<?php

namespace App\Services;

use DOMDocument;
use RuntimeException;

class XlsxReader
{
    public function readSheet(string $filePath, string $sheetName): array
    {
        $archive = XlsxZipArchive::open($filePath);
        $sheetPath = $this->resolveSheetPath($archive, $sheetName);
        $sharedStrings = $this->readSharedStrings($archive);
        $sheetXml = $archive->getFromName($sheetPath);

        if ($sheetXml === null) {
            throw new RuntimeException('A kiválasztott munkalap nem olvasható.');
        }

        return $this->readRows($sheetXml, $sharedStrings);
    }

    /**
     * Ugyanaz, mint readSheet(), csak nem egy adott (elvárt) munkalapnevet
     * keres, hanem egyszerűen a munkafüzet ELSŐ munkalapját olvassa be -
     * olyan importoknál hasznos (pl. óvodai import), ahol nincs egy kötött
     * külső sablon fix munkalapneve, a feltöltő bármit elnevezhet a lapnak.
     */
    public function readFirstSheet(string $filePath): array
    {
        $archive = XlsxZipArchive::open($filePath);
        $sheetPath = $this->resolveFirstSheetPath($archive);
        $sharedStrings = $this->readSharedStrings($archive);
        $sheetXml = $archive->getFromName($sheetPath);

        if ($sheetXml === null) {
            throw new RuntimeException('A munkafüzet első munkalapja nem olvasható.');
        }

        return $this->readRows($sheetXml, $sharedStrings);
    }

    private function resolveFirstSheetPath(XlsxZipArchive $archive): string
    {
        $workbook = $this->loadXml($archive, 'xl/workbook.xml');
        $relationships = $this->loadXml($archive, 'xl/_rels/workbook.xml.rels');
        $relationshipId = null;

        foreach ($workbook->getElementsByTagNameNS('*', 'sheet') as $sheet) {
            $relationshipId = $sheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id'
            );
            break;
        }

        if (!$relationshipId) {
            throw new RuntimeException('A munkafüzetben nincs egyetlen munkalap sem.');
        }

        foreach ($relationships->getElementsByTagNameNS('*', 'Relationship') as $relationship) {
            if ($relationship->getAttribute('Id') === $relationshipId) {
                $target = str_replace('\\', '/', $relationship->getAttribute('Target'));

                return str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/'.ltrim($target, '/');
            }
        }

        throw new RuntimeException('A munkalap belső hivatkozása hiányzik.');
    }

    private function resolveSheetPath(XlsxZipArchive $archive, string $sheetName): string
    {
        $workbook = $this->loadXml($archive, 'xl/workbook.xml');
        $relationships = $this->loadXml($archive, 'xl/_rels/workbook.xml.rels');
        $relationshipId = null;

        foreach ($workbook->getElementsByTagNameNS('*', 'sheet') as $sheet) {
            if ($sheet->getAttribute('name') === $sheetName) {
                $relationshipId = $sheet->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                    'id'
                );
                break;
            }
        }

        if (!$relationshipId) {
            throw new RuntimeException("A(z) „{$sheetName}” munkalap nem található az Excelben.");
        }

        foreach ($relationships->getElementsByTagNameNS('*', 'Relationship') as $relationship) {
            if ($relationship->getAttribute('Id') === $relationshipId) {
                $target = str_replace('\\', '/', $relationship->getAttribute('Target'));

                return str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/'.ltrim($target, '/');
            }
        }

        throw new RuntimeException('A munkalap belső hivatkozása hiányzik.');
    }

    private function readSharedStrings(XlsxZipArchive $archive): array
    {
        $xml = $archive->getFromName('xl/sharedStrings.xml');

        if ($xml === null) {
            return [];
        }

        $document = $this->parseXml($xml);
        $strings = [];

        foreach ($document->getElementsByTagNameNS('*', 'si') as $item) {
            $value = '';

            foreach ($item->getElementsByTagNameNS('*', 't') as $textNode) {
                $value .= $textNode->textContent;
            }

            $strings[] = $value;
        }

        return $strings;
    }

    private function readRows(string $xml, array $sharedStrings): array
    {
        $document = $this->parseXml($xml);
        $rows = [];

        foreach ($document->getElementsByTagNameNS('*', 'row') as $rowNode) {
            $row = [];

            foreach ($rowNode->getElementsByTagNameNS('*', 'c') as $cell) {
                $reference = $cell->getAttribute('r');
                $columnIndex = $this->columnIndex($reference);
                $type = $cell->getAttribute('t');
                $valueNode = $cell->getElementsByTagNameNS('*', 'v')->item(0);
                $value = $valueNode?->textContent;

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? null;
                } elseif ($type === 'inlineStr') {
                    $text = '';
                    foreach ($cell->getElementsByTagNameNS('*', 't') as $textNode) {
                        $text .= $textNode->textContent;
                    }
                    $value = $text;
                } elseif ($type === 'b') {
                    $value = $value === '1' ? 'Igen' : 'Nem';
                }

                $row[$columnIndex] = is_string($value) ? trim($value) : $value;
            }

            if ($row !== []) {
                $maxColumn = max(array_keys($row));
                $rows[] = array_replace(array_fill(0, $maxColumn + 1, null), $row);
            }
        }

        return $rows;
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }

        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function loadXml(XlsxZipArchive $zip, string $path): DOMDocument
    {
        $xml = $zip->getFromName($path);

        if ($xml === null) {
            throw new RuntimeException("Hiányzó Excel-összetevő: {$path}");
        }

        return $this->parseXml($xml);
    }

    private function parseXml(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('Az Excel-fájl egyik XML-összetevője sérült.');
        }

        return $document;
    }
}

final class XlsxZipArchive
{
    /** @var array<string, array{compression:int, compressedSize:int, uncompressedSize:int, localHeaderOffset:int}> */
    private array $entries = [];

    private function __construct(private readonly string $filePath)
    {
    }

    public static function open(string $filePath): self
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Az Excel-fájl nem nyitható meg.');
        }

        $archive = new self($filePath);
        $archive->loadCentralDirectory();

        return $archive;
    }

    public function getFromName(string $path): ?string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $entry = $this->entries[$normalizedPath] ?? null;

        if ($entry === null) {
            return null;
        }

        $handle = fopen($this->filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Az Excel-fájl nem nyitható meg.');
        }

        try {
            if (fseek($handle, $entry['localHeaderOffset']) !== 0) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            $localHeader = fread($handle, 30);
            if ($localHeader === false || strlen($localHeader) !== 30) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            $header = unpack(
                'Vsignature/vversion/vflags/vcompression/vmodTime/vmodDate/Vcrc/Vcompressed/Vuncompressed/vfileNameLength/vextraFieldLength',
                $localHeader
            );

            if (!is_array($header) || $header['signature'] !== 0x04034b50) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            if (($header['flags'] & 0x0001) !== 0) {
                throw new RuntimeException('A titkosított Excel-fájlok importálása nem támogatott.');
            }

            $dataOffset = $entry['localHeaderOffset'] + 30 + $header['fileNameLength'] + $header['extraFieldLength'];

            if (fseek($handle, $dataOffset) !== 0) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            $compressedData = $entry['compressedSize'] > 0
                ? fread($handle, $entry['compressedSize'])
                : '';

            if ($compressedData === false || strlen($compressedData) !== $entry['compressedSize']) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            return $this->decompressEntry($entry['compression'], $compressedData, $entry['uncompressedSize']);
        } finally {
            fclose($handle);
        }
    }

    private function loadCentralDirectory(): void
    {
        $fileSize = filesize($this->filePath);

        if ($fileSize === false || $fileSize < 22) {
            throw new RuntimeException('Az Excel-fájl nem nyitható meg.');
        }

        $handle = fopen($this->filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Az Excel-fájl nem nyitható meg.');
        }

        try {
            $searchLength = (int) min($fileSize, 65557);
            if (fseek($handle, $fileSize - $searchLength) !== 0) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            $tail = fread($handle, $searchLength);
            if ($tail === false) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            $eocdOffset = strrpos($tail, "\x50\x4b\x05\x06");
            if ($eocdOffset === false) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            $eocd = substr($tail, $eocdOffset, 22);
            if (strlen($eocd) !== 22) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            $record = unpack(
                'Vsignature/vdiskNumber/vcentralDirectoryDisk/ventriesOnDisk/ventryCount/VcentralDirectorySize/VcentralDirectoryOffset/vcommentLength',
                $eocd
            );

            if (!is_array($record) || $record['signature'] !== 0x06054b50) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            if ($record['entryCount'] === 0) {
                throw new RuntimeException('Az Excel-fájl üres ZIP-archívum.');
            }

            if (
                $record['entryCount'] === 0xffff
                || $record['centralDirectorySize'] === 0xffffffff
                || $record['centralDirectoryOffset'] === 0xffffffff
            ) {
                throw new RuntimeException('A ZIP64 Excel-fájlok importálása nem támogatott.');
            }

            if (fseek($handle, $record['centralDirectoryOffset']) !== 0) {
                throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
            }

            for ($index = 0; $index < $record['entryCount']; $index++) {
                $headerData = fread($handle, 46);
                if ($headerData === false || strlen($headerData) !== 46) {
                    throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
                }

                $header = unpack(
                    'Vsignature/vversionMadeBy/vversionNeeded/vflags/vcompression/vmodTime/vmodDate/Vcrc/Vcompressed/Vuncompressed/vfileNameLength/vextraFieldLength/vfileCommentLength/vdiskNumberStart/vinternalAttributes/VexternalAttributes/VlocalHeaderOffset',
                    $headerData
                );

                if (!is_array($header) || $header['signature'] !== 0x02014b50) {
                    throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
                }

                if (($header['flags'] & 0x0001) !== 0) {
                    throw new RuntimeException('A titkosított Excel-fájlok importálása nem támogatott.');
                }

                if ($header['compressed'] === 0xffffffff || $header['uncompressed'] === 0xffffffff || $header['localHeaderOffset'] === 0xffffffff) {
                    throw new RuntimeException('A ZIP64 Excel-fájlok importálása nem támogatott.');
                }

                $fileName = fread($handle, $header['fileNameLength']);
                if ($fileName === false || strlen($fileName) !== $header['fileNameLength']) {
                    throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
                }

                $normalizedName = str_replace('\\', '/', $fileName);
                $skipLength = $header['extraFieldLength'] + $header['fileCommentLength'];

                if ($skipLength > 0 && fseek($handle, $skipLength, SEEK_CUR) !== 0) {
                    throw new RuntimeException('Az Excel-fájl ZIP szerkezete sérült.');
                }

                if (str_ends_with($normalizedName, '/')) {
                    continue;
                }

                $this->entries[$normalizedName] = [
                    'compression' => $header['compression'],
                    'compressedSize' => $header['compressed'],
                    'uncompressedSize' => $header['uncompressed'],
                    'localHeaderOffset' => $header['localHeaderOffset'],
                ];
            }
        } finally {
            fclose($handle);
        }
    }

    private function decompressEntry(int $compression, string $compressedData, int $uncompressedSize): string
    {
        return match ($compression) {
            0 => $compressedData,
            8 => $this->inflate($compressedData, $uncompressedSize),
            default => throw new RuntimeException("Nem támogatott ZIP tömörítési mód az Excel-fájlban: {$compression}"),
        };
    }

    private function inflate(string $compressedData, int $uncompressedSize): string
    {
        $data = $compressedData === '' ? '' : gzinflate($compressedData);

        if (!is_string($data)) {
            throw new RuntimeException('Az Excel-fájl egyik ZIP-bejegyzése nem bontató ki.');
        }

        if (strlen($data) !== $uncompressedSize) {
            throw new RuntimeException('Az Excel-fájl egyik ZIP-bejegyzése sérült.');
        }

        return $data;
    }
}
