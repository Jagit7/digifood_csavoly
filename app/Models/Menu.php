<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
	protected $fillable = [
		'institution_id',
		'type',
		'title',
		'week_start',
		'week_end',
		'has_saturday',
		'saturday_date',
		'saturday_note',
		'file_path',
		'file_name',
		'mime_type',
		'file_size',
		'active',
		'published_at',
		'created_by',
		'updated_by',
	];

	protected $casts = [
		'week_start' => 'date',
		'week_end' => 'date',
		'has_saturday' => 'boolean',
		'saturday_date' => 'date',
		'active' => 'boolean',
		'published_at' => 'datetime',
	];

	public function institution()
	{
		return $this->belongsTo(Institution::class);
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function updater()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	/**
	 * Az étlaphoz tartozó fájl kiterjesztése (fájlnévből, kisbetűvel).
	 * Csak akkor használjuk döntéshez, ha nincs megbízható MIME-type mentve.
	 */
	public function previewExtension(): string
	{
		return strtolower(pathinfo($this->file_name ?: $this->file_path ?: '', PATHINFO_EXTENSION));
	}

	/**
	 * Valódi kép-e az étlap fájlja (előnézetben <img>-ként jeleníthető meg).
	 * Elsődlegesen a mentett MIME-type alapján dönt, csak hiányzó/ismeretlen
	 * MIME-type esetén esik vissza a fájlkiterjesztésre.
	 */
	public function isPreviewableImage(): bool
	{
		if ($this->mime_type) {
			return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
		}

		return in_array($this->previewExtension(), ['jpg', 'jpeg', 'png', 'webp'], true);
	}

	/**
	 * PDF-e az étlap fájlja (előnézetben iframe-ben jeleníthető meg).
	 */
	public function isPreviewablePdf(): bool
	{
		if ($this->mime_type) {
			return $this->mime_type === 'application/pdf';
		}

		return $this->previewExtension() === 'pdf';
	}

	/**
	 * A fájl előnézeti/letöltési válaszához használandó Content-Type.
	 * A mentett MIME-type a mérvadó; csak ha az hiányzik, becsüljük meg
	 * a kiterjesztés alapján.
	 */
	public function previewMimeType(): string
	{
		if ($this->mime_type) {
			return $this->mime_type;
		}

		return match ($this->previewExtension()) {
			'pdf' => 'application/pdf',
			'jpg', 'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			default => 'application/octet-stream',
		};
	}
}