<?php

namespace Tests\Unit;

use App\Services\XlsxReader;
use PHPUnit\Framework\TestCase;

class XlsxReaderTest extends TestCase
{
    public function test_it_reads_first_sheet_and_preserves_cell_types_and_gaps(): void
    {
        $filePath = $this->createWorkbook([
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/sharedStrings.xml' => $this->sharedStringsXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetOneXml(),
            'xl/worksheets/sheet2.xml' => $this->sheetTwoXml(),
        ]);

        try {
            $rows = (new XlsxReader())->readFirstSheet($filePath);

            $this->assertSame(
                ['Árvíztűrő tükörfúrógép', null, 'Óvoda', null, '42', 'Igen'],
                $rows[0]
            );
            $this->assertSame([null, 'Első sor', null, 'Nem'], $rows[1]);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_it_reads_named_sheet(): void
    {
        $filePath = $this->createWorkbook([
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/sharedStrings.xml' => $this->sharedStringsXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetOneXml(),
            'xl/worksheets/sheet2.xml' => $this->sheetTwoXml(),
        ]);

        try {
            $rows = (new XlsxReader())->readSheet($filePath, 'Második lap');

            $this->assertSame([null, 'Iskola'], $rows[0]);
            $this->assertSame(['123', null, 'Igen'], $rows[1]);
        } finally {
            @unlink($filePath);
        }
    }

    /**
     * @param array<string, string> $entries
     */
    private function createWorkbook(array $entries): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'xlsx-reader-');
        if ($filePath === false) {
            self::fail('Nem sikerült ideiglenes fájlt létrehozni.');
        }

        $xlsxPath = $filePath.'.xlsx';
        if (!@rename($filePath, $xlsxPath)) {
            @unlink($filePath);
            self::fail('Nem sikerült XLSX ideiglenes fájlt létrehozni.');
        }

        file_put_contents($xlsxPath, $this->buildZip($entries));

        return $xlsxPath;
    }

    /**
     * @param array<string, string> $entries
     */
    private function buildZip(array $entries): string
    {
        $offset = 0;
        $localFiles = '';
        $centralDirectory = '';
        $entryCount = 0;

        foreach ($entries as $name => $contents) {
            $compressed = gzdeflate($contents);
            if ($compressed === false) {
                self::fail("Nem sikerült tömöríteni a(z) {$name} ZIP-bejegyzést.");
            }

            $nameLength = strlen($name);
            $compressedLength = strlen($compressed);
            $uncompressedLength = strlen($contents);
            $crc = crc32($contents);

            $localFiles .= pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                8,
                0,
                0,
                $crc,
                $compressedLength,
                $uncompressedLength,
                $nameLength,
                0
            ).$name.$compressed;

            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                8,
                0,
                0,
                $crc,
                $compressedLength,
                $uncompressedLength,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $offset
            ).$name;

            $offset += 30 + $nameLength + $compressedLength;
            $entryCount++;
        }

        $centralDirectorySize = strlen($centralDirectory);

        return $localFiles
            .$centralDirectory
            .pack(
                'VvvvvVVv',
                0x06054b50,
                0,
                0,
                $entryCount,
                $entryCount,
                $centralDirectorySize,
                $offset,
                0
            );
    }

    private function contentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML;
    }

    private function rootRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Első lap" sheetId="1" r:id="rId1"/>
    <sheet name="Második lap" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>
XML;
    }

    private function workbookRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML;
    }

    private function sharedStringsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3">
  <si><t>Árvíztűrő tükörfúrógép</t></si>
  <si>
    <r><t>Első</t></r>
    <r><t xml:space="preserve"> sor</t></r>
  </si>
  <si><t>Iskola</t></si>
</sst>
XML;
    }

    private function sheetOneXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="C1" t="inlineStr"><is><t>Óvoda</t></is></c>
      <c r="E1"><v>42</v></c>
      <c r="F1" t="b"><v>1</v></c>
    </row>
    <row r="2">
      <c r="B2" t="s"><v>1</v></c>
      <c r="D2" t="b"><v>0</v></c>
    </row>
  </sheetData>
</worksheet>
XML;
    }

    private function sheetTwoXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="B1" t="s"><v>2</v></c>
    </row>
    <row r="2">
      <c r="A2"><v>123</v></c>
      <c r="C2" t="b"><v>1</v></c>
    </row>
  </sheetData>
</worksheet>
XML;
    }
}
