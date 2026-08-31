<?php

namespace App\Http\Controllers;

use App\Mail\InstitutionAdminInviteMail;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\InstitutionAdminInvitation;
use App\Models\User;
use App\Support\AdminInstitutionContext;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminInstitutionAccessController extends Controller
{
    public function __construct(
        private readonly AdminInstitutionContext $institutionContext
    ) {}

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
                    $q->whereIn('users.role', $this->manageableRoles)
                        ->when($role, fn ($qq) => $qq->wherePivot('scope_role', $role))
                        ->orderBy('name');
                },
                'adminInvitations' => function ($q) use ($role) {
                    $q->when($role, fn ($qq) => $qq->where('role', $role))
                        ->whereNull('accepted_at')
                        ->orderByDesc('created_at');
                },
            ])
            ->orderBy('name')
            ->get();

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
        if (! in_array($user->role, $this->manageableRoles)) {
            abort(404);
        }

        $institutions = Institution::query()
            ->orderBy('name')
            ->get(['id', 'name', 'institution_code']);

        $selectedIds = $user->institutions()
            ->pluck('institutions.id')
            ->toArray();

        $roleLabels = $this->roleLabels();

        return view(
            'dashboard.superadmin.admin_access.edit',
            compact('user', 'institutions', 'selectedIds', 'roleLabels')
        );
    }

    public function update(Request $request, User $user)
    {
        if (! in_array($user->role, $this->manageableRoles)) {
            abort(404);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'is_active' => ['nullable', 'boolean'],
            'institutions' => ['nullable', 'array'],
            'institutions.*' => ['integer', 'exists:institutions,id'],
            'password' => ['nullable', 'confirmed', 'min:8'],
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
            $rules['role'] = ['required', 'in:institution_admin,institution_secretary,kitchen,municipality'];
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
            'password' => ! empty($validated['password'])
                ? Hash::make($validated['password'])
                : $user->password,
        ])->save();

        $existingScopeRoles = $user->institutions()
            ->pluck('institution_user.scope_role', 'institutions.id');

        $user->institutions()->sync(
            collect($validated['institutions'] ?? [])
                ->mapWithKeys(fn (int $institutionId) => [
                    $institutionId => ['scope_role' => $existingScopeRoles[$institutionId] ?? $role],
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

        [$invitation, $url, $attachedExistingUser] = DB::transaction(function () use ($validated) {
            $now = now();
            $plainToken = Str::random(64);

            $invitation = InstitutionAdminInvitation::query()->create([
                'institution_id' => $validated['institution_id'],
                'invited_by' => auth()->id(),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $now->copy()->addHours(InstitutionAdminInvitation::EXPIRES_IN_HOURS),
            ]);

            $existingUser = User::query()
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            if (! $this->canAttachInstitutionImmediately($existingUser)) {
                return [$invitation->fresh('institution'), $this->sendInvitationEmail($invitation->fresh('institution'), $plainToken), false];
            }

            $existingUser->institutions()->syncWithoutDetaching([
                $validated['institution_id'] => ['scope_role' => $validated['role']],
            ]);

            $existingUser->forceFill([
                'accepted_invitation_at' => $existingUser->accepted_invitation_at ?? $now,
                'email_verified_at' => $existingUser->email_verified_at ?? $now,
                'is_active' => true,
            ])->save();

            $invitation->forceFill([
                'accepted_at' => $now,
            ])->save();

            return [$invitation->fresh('institution'), null, true];
        });

        $redirect = redirect()
            ->route('dashboard.admin-access.index')
            ->with(
                'success',
                $attachedExistingUser
                    ? 'A meglévő, aktív felhasználó azonnal hozzá lett rendelve az intézményhez. Nem küldtünk új aktiváló meghívót.'
                    : 'Meghívó létrehozva és e-mailben elküldve.'
            );

        if ($url !== null) {
            $redirect->with('invite_url', $url);
        }

        return $redirect;
    }

    public function resendInvite(InstitutionAdminInvitation $invitation)
    {
        if ($invitation->isAccepted()) {
            return back()->withErrors([
                'invitation' => 'Az elfogadott meghívó nem küldhető újra.',
            ]);
        }

        if (! $invitation->isExpired()) {
            return back()->withErrors([
                'invitation' => 'Csak lejárt, még el nem fogadott meghívó küldhető újra.',
            ]);
        }

        $plainToken = Str::random(64);
        $now = now();

        $invitation = DB::transaction(function () use ($invitation, $plainToken, $now) {
            $lockedInvitation = InstitutionAdminInvitation::query()
                ->with('institution')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($lockedInvitation->isAccepted()) {
                abort(422, 'Az elfogadott meghívó nem küldhető újra.');
            }

            if (! $lockedInvitation->isExpired()) {
                abort(422, 'Csak lejárt, még el nem fogadott meghívó küldhető újra.');
            }

            $previousExpiresAt = $lockedInvitation->expires_at;

            $lockedInvitation->forceFill([
                'invited_by' => auth()->id(),
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $now->copy()->addHours(InstitutionAdminInvitation::EXPIRES_IN_HOURS),
                'accepted_at' => null,
            ])->save();

            AuditLogger::log(
                action: AuditLog::ACTION_INSTITUTION_ADMIN_INVITATION_RESENT,
                description: 'Intézményi admin meghívó újraküldve',
                subject: $lockedInvitation,
                institutionId: $lockedInvitation->institution_id,
                oldValues: [
                    'invitation_id' => $lockedInvitation->id,
                    'institution_id' => $lockedInvitation->institution_id,
                    'email' => $lockedInvitation->email,
                    'expires_at' => $previousExpiresAt?->toDateTimeString(),
                ],
                newValues: [
                    'invitation_id' => $lockedInvitation->id,
                    'institution_id' => $lockedInvitation->institution_id,
                    'email' => $lockedInvitation->email,
                    'resent_by_user_id' => auth()->id(),
                    'expires_at' => $lockedInvitation->expires_at?->toDateTimeString(),
                ]
            );

            return $lockedInvitation->fresh('institution');
        });

        $url = $this->sendInvitationEmail($invitation, $plainToken);

        return back()
            ->with('success', 'A lejárt meghívó újraküldése sikeres volt.')
            ->with('invite_url', $url);
    }

    public function acceptInvite(string $token)
    {
        $invitation = $this->findInvitationByToken($token);

        if ($response = $this->invitationStateResponse($invitation)) {
            return $response;
        }

        return view('auth.accept_institution_invite', compact('token', 'invitation'));
    }

    public function completeInvite(Request $request, string $token)
    {
        $validated = $request->validate([
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);

        [$user, $invitation] = DB::transaction(function () use ($token, $validated) {
            $invitation = $this->findInvitationByToken($token, lockForUpdate: true);

            if ($response = $this->invitationStateResponse($invitation)) {
                return [$response, null];
            }

            $now = now();
            $existingUser = User::query()
                ->where('email', $invitation->email)
                ->lockForUpdate()
                ->first();

            if ($existingUser !== null) {
                if ($this->canAttachInstitutionImmediately($existingUser)) {
                    $existingUser->forceFill([
                        'accepted_invitation_at' => $existingUser->accepted_invitation_at ?? $now,
                        'email_verified_at' => $existingUser->email_verified_at ?? $now,
                        'is_active' => true,
                    ])->save();

                    $user = $existingUser;
                } elseif (! auth()->check() || auth()->user()->email !== $invitation->email) {
                    abort(403, 'Ehhez az e-mail címhez már tartozik fiók a rendszerben. Jelentkezz be a meglévő fiókoddal, majd vedd fel a kapcsolatot a rendszergazdával az intézményi hozzáférés beállításához.');
                } else {
                    $user = $existingUser;
                }
            } else {
                if (blank($validated['password'] ?? null)) {
                    abort(422, 'A jelszó megadása kötelező.');
                }

                $user = User::forceCreate([
                    'email' => $invitation->email,
                    'name' => $invitation->name,
                    'role' => $invitation->role,
                    'password' => Hash::make($validated['password']),
                    'is_active' => true,
                    'accepted_invitation_at' => $now,
                    'email_verified_at' => $now,
                ]);
            }

            $user->institutions()->syncWithoutDetaching([
                $invitation->institution_id => ['scope_role' => $invitation->role],
            ]);

            $invitation->forceFill([
                'accepted_at' => $now,
            ])->save();

            return [$user, $invitation->fresh('institution')];
        });

        if ($user instanceof Response) {
            return $user;
        }

        auth()->login($user);
        $this->institutionContext->switchToInstitution($user, (int) $invitation->institution_id);

        if ($user->role === 'super_admin') {
            return redirect()->to(route('dashboard.superadmin', [], false))
                ->with('success', 'Fiók létrehozva, beléptél.');
        }

        return redirect()->to(route('dashboard.institution.home', [], false))
            ->with('success', 'Fiók létrehozva, beléptél.');
    }

    public function toggleActive(User $user)
    {
        if (! in_array($user->role, $this->manageableRoles)) {
            abort(404);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Saját magadat nem tudod inaktiválni.']);
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
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

    private function findInvitationByToken(string $token, bool $lockForUpdate = false): InstitutionAdminInvitation
    {
        $query = InstitutionAdminInvitation::query()
            ->with('institution')
            ->where('token_hash', hash('sha256', $token));

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function invitationStateResponse(InstitutionAdminInvitation $invitation): ?Response
    {
        if ($invitation->isAccepted()) {
            return response()->view('auth.institution_invitation_state', [
                'title' => 'Ez a meghívó már fel lett használva.',
                'lead' => 'A meghívó egyszer használható fel.',
                'message' => 'Ha továbbra is szüksége van hozzáférésre, kérjük, vegye fel a kapcsolatot az intézménnyel vagy a Digifood adminisztrátorával.',
            ], 410);
        }

        if ($invitation->isExpired()) {
            return response()->view('auth.institution_invitation_state', [
                'title' => 'Ez a meghívó lejárt.',
                'lead' => 'A meghívó 24 órán keresztül használható.',
                'message' => 'Kérjük, kérjen új meghívót az intézmény vagy a Digifood adminisztrátorától.',
            ], 410);
        }

        return null;
    }

    private function sendInvitationEmail(InstitutionAdminInvitation $invitation, string $plainToken): string
    {
        $url = route('institution-invite.accept', ['token' => $plainToken]);

        Mail::to($invitation->email)->send(new InstitutionAdminInviteMail([
            'recipientName' => $invitation->name,
            'institutionName' => $invitation->institution->name,
            'roleLabel' => $this->roleLabels()[$invitation->role] ?? $invitation->role,
            'activationUrl' => $url,
            'expiresAt' => $invitation->expires_at,
        ]));

        return $url;
    }

    private function canAttachInstitutionImmediately(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! in_array($user->role, $this->manageableRoles, true)) {
            return false;
        }

        return $user->is_active
            && filled($user->password)
            && $user->accepted_invitation_at !== null;
    }
}
