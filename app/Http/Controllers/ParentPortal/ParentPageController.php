<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ParentPageController extends Controller
{
    private const PAGES = [
        'meal-cancellations' => [
            'title' => 'Étkezések és lemondások',
            'description' => 'Ez az oldal előkészítve várja a szülői étkezéskezelési és lemondási funkciókat.',
        ],
        'statements' => [
            'title' => 'Havi elszámolások',
            'description' => 'Ez az oldal a havi elszámolások későbbi megjelenítéséhez készült elő.',
        ],
        'payments' => [
            'title' => 'Befizetések',
            'description' => 'Ez az oldal a befizetések szülői nézetének helyét készíti elő.',
        ],
        'invoices' => [
            'title' => 'Számlák',
            'description' => 'Ez az oldal a szülőhöz kapcsolt számlák későbbi megjelenítéséhez készült.',
        ],
        'account' => [
            'title' => 'Fiókom',
            'description' => 'Ez az oldal a szülői fiókbeállítások és profiladatok számára van előkészítve.',
        ],
    ];

    public function show(string $page): View
    {
        $config = self::PAGES[$page] ?? throw new NotFoundHttpException();

        return view('parent.pages.placeholder', [
            'title' => $config['title'],
            'description' => $config['description'],
        ]);
    }
}
