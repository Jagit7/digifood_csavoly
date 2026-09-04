<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Models\InstitutionAdminInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AdminInstitutionAccessController extends Controller
{
    /** Kik számítanak "adminnak", akiket itt kezelünk */
    private array $manageableRoles = [
        User::ROLE_INSTITUTION_ADMIN,
        User::ROLE_INSTITUTION_SECRETARY,
        User::ROLE_KITCHEN,
        User::ROLE_MUNICIPALITY,
        User::ROLE_SUPER_ADMIN,
    ];

    public function index(Request $request)
    {
        $role = $request->get('role');

        $roleLabels = $this->roleLabels();

        $institutions = Institution::query()
            ->with([
                'users' => function ($q) use ($role) {
                    $q->when($role, fn($qq) => $qq->where('role', $role))
                        ->whereIn('role', $this->manageableRoles)
                        ->orderBy('name');
                },
                'adminInvitations' => function ($q) use ($role) {
                    $q->when($role, fn($qq) => $qq->where('role', $role))
                        ->whereNull('accepted_at')
                        ->orderByDesc('created_at');
                },
            ])
            ->orderBy('name')
            ->get();

        $activeAccessEmails = User::query()
            ->join('institution_user', 'institution_user.user_id', '=', 'users.id')
            ->where('users.is_active', true)
            ->whereIn('institution_user.institution_id', $institutions->pluck('id'))
            ->get(['institution_user.institution_id', 'users.email'])
            ->groupBy('institution_id')
            ->map(fn ($users) => $users->pluck('email')
                ->map(fn (string $email) => mb_strtolower($email))
                ->flip());

        $institutions->each(function (Institution $institution) use ($activeAccessEmails): void {
            $emails = $activeAccessEmails->get($institution->id, collect());

            $institution->setRelation('adminInvitations', $institution->adminInvitations
                ->reject(fn (InstitutionAdminInvitation $invitation) => $emails->has(mb_strtolower($invitation->email)))
                ->values());
        });

        $roles = $this->manageableRoles;

        return view('dashboard.superadmin.admin_access.index', compact(
            'institutions',
            'roles',
            'role',
            'roleLabels'
        ));
    }

    public function edit(User $user)
    {
        if (!in_array($user->role, $this->manageableRoles)) {
            abort(404);
        }

        $institutions = Institution::query()
            ->orderBy('name')
            ->get(['id','name','institution_code']);

        $selectedIds = $user->institutions()
            ->pluck('institutions.id')
            ->toArray();

        $roleLabels = $this->roleLabels();

        return view(
            'dashboard.superadmin.admin_access.edit',
            compact('user','institutions','selectedIds','roleLabels')
        );
    }

    public function update(Request $request, User $user)
    {
        if (!in_array($user->role, $this->manageableRoles)) {
            abort(404);
        }

        $rules = [
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email,' . $user->id],
            'is_active' => ['nullable','boolean'],
            'institutions' => ['nullable','array'],
            'institutions.*' => ['integer','exists:institutions,id'],
            'password' => ['nullable','confirmed','min:8'],
        ];

        // A szuperadmin szerepkörét ezen a felületen nem lehet módosítani (sem
        // felvenni, sem levenni) - a role select csak a "sima" adminisztratív
        // szerepkörök közül enged választani, ezért ha a szerkesztett
        // felhasználó jelenleg superadmin, megtartjuk a szerepkörét, nehogy
        // egy egyszerű névmódosítás véletlenül lefokozza.
        if ($user->role === User::ROLE_SUPER_ADMIN) {
            $validated = $request->validate($rules);
            $role = User::ROLE_SUPER_ADMIN;
        } else {
            $rules['role'] = ['required','in:institution_admin,institution_secretary,kitchen,municipality'];
            $validated = $request->validate($rules);
            $role = $validated['role'];
        }

        // forceFill(): a "role"/"is_active" mezők tudatosan nincsenek a
        // User modell fillable listájában (jogosultság-eszkalációs
        // védelem), itt viszont ez a jogszerű, kontrollált hely, ahol
        // ezeket be kell tudni állítani.
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $role,
            'is_active' => $request->boolean('is_active'),
            'password' => !empty($validated['password'])
                ? Hash::make($validated['password'])
                : $user->password,
        ])->save();

        $user->institutions()->sync(
            collect($validated['institutions'] ?? [])
                ->mapWithKeys(fn (int $institutionId) => [
                    $institutionId => ['scope_role' => $role],
                ])
                ->all()
        );

        return redirect()
            ->route('dashboard.admin-access.index')
            ->with('success', 'Felhasználó adatai frissítve.');
    }

    public function createInvite()
    {
        $institutions = Institution::query()
            ->orderBy('name')
            ->get(['id', 'name', 'institution_code']);

        return view('dashboard.superadmin.admin_access.invite', compact('institutions'));
    }

    public function storeInvite(Request $request)
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:institution_admin,institution_secretary,kitchen,municipality'],
        ]);

        $email = mb_strtolower(trim($validated['email']));

        $hasActiveInstitutionAccess = User::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereHas('institutions', fn ($query) => $query->where('institutions.id', $validated['institution_id']))
            ->exists();

        if ($hasActiveInstitutionAccess) {
            return back()
                ->withErrors(['email' => 'Ezzel az e-mail-címmel már létezik aktív intézményi felhasználó.'])
                ->withInput();
        }

        $hasPendingInvitation = InstitutionAdminInvitation::query()
            ->where('institution_id', $validated['institution_id'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasPendingInvitation) {
            return back()
                ->withErrors(['email' => 'Ehhez az e-mail-címhez már van érvényes, elfogadásra váró meghívó.'])
                ->withInput();
        }

        $plainToken = Str::random(64);

        InstitutionAdminInvitation::create([
            'institution_id' => $validated['institution_id'],
            'invited_by' => auth()->id(),
            'name' => $validated['name'],
            'email' => $email,
            'role' => $validated['role'],
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);

        $url = route('institution-invite.accept', ['token' => $plainToken]);

        return redirect()
            ->route('dashboard.admin-access.index')
            ->with('success', 'Meghívó létrehozva.')
            ->with('invite_url', $url);
    }

    public function acceptInvite(string $token)
    {
        $invitation = InstitutionAdminInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->firstOrFail();

        if ($invitation->expires_at->isPast()) {
            abort(403, 'A meghívó link lejárt.');
        }

        return view('auth.accept_institution_invite', compact('token', 'invitation'));
    }

    public function completeInvite(Request $request, string $token)
    {
        $invitation = InstitutionAdminInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->firstOrFail();

        if ($invitation->expires_at->isPast()) {
            abort(403, 'A meghívó link lejárt.');
        }

        $now = now();

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])
            ->first();

        if ($existingUser !== null) {
            // A token önmagában nem írhat hozzáférést egy meglévő fiókhoz. A
            // meghívott saját jelszava viszont akkor is igazolja a fiókot, ha a
            // böngészőben közben egy másik Digifood-felhasználó van belépve.
            $validated = $request->validate([
                'password' => ['required'],
            ]);

            if (!Hash::check($validated['password'], $existingUser->password)) {
                return back()
                    ->withErrors(['password' => 'A meglévő fiók jelszava nem megfelelő.'])
                    ->withInput();
            }

            $user = $existingUser;

            $user->institutions()->syncWithoutDetaching([
                $invitation->institution_id => ['scope_role' => $invitation->role],
            ]);
        } else {
            $validated = $request->validate([
                'password' => ['required', 'confirmed', 'min:8'],
            ]);

            // forceCreate(): ld. fenti megjegyzés - "role"/"is_active" nem
            // fillable a User modellen.
            $user = User::forceCreate([
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'accepted_invitation_at' => $now,
                'email_verified_at' => $now,
            ]);

            $user->institutions()->syncWithoutDetaching([
                $invitation->institution_id => ['scope_role' => $invitation->role],
            ]);
        }

        $invitation->update([
            'accepted_at' => $now,
        ]);

        auth()->login($user);

        if ($user->role === 'super_admin') {
            return redirect()->route('dashboard.superadmin')
                ->with('success', 'Fiók létrehozva, beléptél.');
        }

        return redirect()->route('dashboard.institution.home')
            ->with('success', 'Fiók létrehozva, beléptél.');
    }

    public function toggleActive(User $user)
    {
        if (!in_array($user->role, $this->manageableRoles)) {
            abort(404);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Saját magadat nem tudod inaktiválni.']);
        }

        $user->forceFill([
            'is_active' => !$user->is_active,
        ])->save();

        return back()->with(
            'success',
            $user->is_active ? 'Felhasználó aktiválva.' : 'Felhasználó inaktiválva.'
        );
    }

    private function roleLabels(): array
    {
        return [
            User::ROLE_SUPER_ADMIN => 'Szuperadmin',
            User::ROLE_INSTITUTION_ADMIN => 'Intézményi admin',
            User::ROLE_INSTITUTION_SECRETARY => 'Intézményi titkár',
            User::ROLE_KITCHEN => 'Konyha',
            User::ROLE_MUNICIPALITY => 'Önkormányzat',
        ];
    }
}
