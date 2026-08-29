<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ParentMenuController extends Controller
{
    public function index(Request $request): View
    {
        $institutionIds = $this->accessibleInstitutionIds($request->user());
        $latestPublishedMenu = Menu::query()
            ->where('active', true)
            ->whereIn('institution_id', $institutionIds)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        $menus = Menu::query()
            ->with('institution')
            ->where('active', true)
            ->whereIn('institution_id', $institutionIds)
            ->orderByDesc('published_at')
            ->orderByDesc('week_start')
            ->orderByDesc('id')
            ->paginate(12)
            ->through(fn (Menu $menu) => $this->mapMenuCard($menu))
            ->withQueryString();

        return view('parent.menus.index', [
            'menus' => $menus,
            'stats' => [
                'menu_count' => Menu::query()
                    ->where('active', true)
                    ->whereIn('institution_id', $institutionIds)
                    ->count(),
                'institution_count' => $institutionIds->count(),
                'latest_published_at_label' => ($latestPublishedMenu?->published_at ?? $latestPublishedMenu?->created_at)
                    ?->timezone(config('app.timezone'))
                    ->format('Y.m.d. H:i') ?? 'Nincs még közzétett étlap',
            ],
        ]);
    }

    public function show(Request $request, Menu $menu): BinaryFileResponse
    {
        $resolvedMenu = $this->resolveAccessibleMenu($request->user(), $menu);
        $absolutePath = $this->resolveExistingMenuPath($resolvedMenu);

        return response()->file($absolutePath, [
            'Content-Type' => $resolvedMenu->mime_type ?: $this->fallbackMimeType($resolvedMenu),
            'Content-Disposition' => 'inline; filename="'.$resolvedMenu->file_name.'"',
        ]);
    }

    public function download(Request $request, Menu $menu): Response
    {
        $resolvedMenu = $this->resolveAccessibleMenu($request->user(), $menu);

        $this->resolveExistingMenuPath($resolvedMenu);

        return Storage::disk('public')->download(
            $resolvedMenu->file_path,
            $resolvedMenu->file_name
        );
    }

    private function resolveAccessibleMenu(User $user, Menu $menu): Menu
    {
        $institutionIds = $this->accessibleInstitutionIds($user);

        return Menu::query()
            ->whereKey($menu->id)
            ->where('active', true)
            ->whereIn('institution_id', $institutionIds)
            ->firstOrFail();
    }

    private function accessibleInstitutionIds(User $user): Collection
    {
        $guardianIds = $user->guardians()
            ->where('active', true)
            ->pluck('guardians.id');

        return Child::query()
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->pluck('institution_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function resolveExistingMenuPath(Menu $menu): string
    {
        if (blank($menu->file_path) || ! Storage::disk('public')->exists($menu->file_path)) {
            abort(404, 'A fájl nem található.');
        }

        return Storage::disk('public')->path($menu->file_path);
    }

    private function fallbackMimeType(Menu $menu): string
    {
        return match ($this->menuExtension($menu)) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function mapMenuCard(Menu $menu): array
    {
        $extension = $this->menuExtension($menu);
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);

        return [
            'id' => $menu->id,
            'title' => $menu->title,
            'institution_name' => $menu->institution?->name,
            'period_label' => $menu->week_start?->format('Y.m.d.').' - '.$menu->week_end?->format('Y.m.d.'),
            'published_at_label' => ($menu->published_at ?? $menu->created_at)?->timezone(config('app.timezone'))->format('Y.m.d. H:i'),
            'file_type_label' => strtoupper($extension ?: 'ismeretlen'),
            'file_size_label' => $menu->file_size ? number_format($menu->file_size / 1024 / 1024, 2, ',', ' ').' MB' : null,
            'type_label' => match ($menu->type) {
                'weekly' => 'Heti étlap',
                'dietary' => 'Diétás étlap',
                'ab' => 'A/B étlap',
                default => $menu->type,
            },
            'is_image' => $isImage,
            'preview_url' => $isImage ? route('parent.menus.show', $menu) : null,
            'view_url' => route('parent.menus.show', $menu),
            'download_url' => route('parent.menus.download', $menu),
            'file_name' => $menu->file_name,
        ];
    }

    private function menuExtension(Menu $menu): string
    {
        return strtolower(pathinfo($menu->file_name ?: $menu->file_path ?: '', PATHINFO_EXTENSION));
    }
}
