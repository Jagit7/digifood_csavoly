<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HandbookController extends Controller
{
    public function index(): View
    {
        $institution = $this->currentAdminInstitution();
        $pageDate = now(config('digifood.business_timezone', config('app.timezone')));

        return view('dashboard.institution_admin.handbook.index', compact('institution', 'pageDate'));
    }
}
