<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionAdminDevice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InstitutionAdminDeviceController extends Controller
{
    public function index(): View
    {
        // Minden intézményi admin felhasználóhoz tartozzon egy sor a
        // listában akkor is, ha még egyáltalán nincs eszköz-kérelme -
        // az egyes felhasználók akár MAX_DEVICES_PER_USER db (jelenleg 2)
        // egymástól független eszköz-sort is birtokolhatnak.
        $users = User::query()
            ->where('role', User::ROLE_INSTITUTION_ADMIN)
            ->with([
                'institution:id,name',
                'devices' => fn ($query) => $query->orderBy('id'),
                'devices.approver:id,name,email',
            ])
            ->orderBy('name')
            ->get();

        return view('dashboard.superadmin.institution_admin_devices.index', [
            'users' => $users,
            'maxDevices' => InstitutionAdminDevice::MAX_DEVICES_PER_USER,
        ]);
    }

    public function approve(InstitutionAdminDevice $institutionAdminDevice): RedirectResponse
    {
        if (! $institutionAdminDevice->hasPendingRequest()) {
            return back()->withErrors(['device' => 'Nincs függőben lévő eszköz-kérelem ehhez a felhasználóhoz.']);
        }

        $institutionAdminDevice->approvePendingRequest(auth()->user());

        return back()->with('success', 'Az eszköz jóváhagyva. A felhasználó ettől a gépről is tud belépni (a felhasználó esetleges másik jóváhagyott eszköze változatlanul érvényes marad).');
    }

    public function reject(InstitutionAdminDevice $institutionAdminDevice): RedirectResponse
    {
        $institutionAdminDevice->rejectPendingRequest();

        return back()->with('success', 'A függőben lévő eszköz-kérelem elutasítva.');
    }

    public function revoke(InstitutionAdminDevice $institutionAdminDevice): RedirectResponse
    {
        $institutionAdminDevice->revokeApprovedDevice();

        return back()->with('success', 'A jóváhagyott eszköz visszavonva. A felhasználónak erről a gépről legközelebb új eszköz-jóváhagyást kell kérnie.');
    }
}
