<?php

namespace App\Http\Middleware;

use App\Models\InstitutionSetting;
use Closure;
use Illuminate\Http\Request;

class EnsureBarcodeEntryEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $institution = $user?->institutions()->first() ?? $user?->institution;

        abort_if($institution === null, 403, 'A kioszk nem kapcsolható intézményhez.');

        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        if (! $setting->barcodeEntryEnabled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'A vonalkódos beléptető modul nem része az intézmény előfizetésének.',
                    'code' => 'module_disabled',
                ], 403);
            }

            abort(403, 'A vonalkódos beléptető modul nem része az intézmény előfizetésének.');
        }

        return $next($request);
    }
}
