<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Support\AdminInstitutionContext;

abstract class Controller
{
    protected function currentAdminInstitution(): Institution
    {
        $institution = app(AdminInstitutionContext::class)->currentInstitution(auth()->user());

        abort_if($institution === null, 403, 'Nincs elérhető intézményi kontextus ehhez a fiókhoz.');

        return $institution;
    }

    protected function currentAdminRole(): ?string
    {
        return app(AdminInstitutionContext::class)->roleFor(auth()->user());
    }
}
