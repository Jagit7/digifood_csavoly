<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('menus', function (Blueprint $table) {
			$table->id();

			$table->foreignId('institution_id')
				->constrained('institutions')
				->cascadeOnDelete();

			$table->enum('type', ['weekly', 'dietary'])->default('weekly');

			$table->string('title');

			$table->date('week_start');
			$table->date('week_end');

			$table->boolean('has_saturday')->default(false);
			$table->date('saturday_date')->nullable();
			$table->string('saturday_note')->nullable();

			$table->string('file_path');
			$table->string('file_name');
			$table->string('mime_type')->nullable();
			$table->unsignedBigInteger('file_size')->nullable();

			$table->boolean('active')->default(true);

			$table->timestamp('published_at')->nullable();

			$table->foreignId('created_by')
				->constrained('users')
				->cascadeOnDelete();

			$table->foreignId('updated_by')
				->nullable()
				->constrained('users')
				->nullOnDelete();

			$table->timestamps();

			$table->index(['institution_id', 'type']);
			$table->index(['institution_id', 'active']);
			$table->index('week_start');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('menus');
	}
};