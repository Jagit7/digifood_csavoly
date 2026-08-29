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
}