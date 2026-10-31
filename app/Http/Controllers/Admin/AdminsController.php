<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalAdmin;
use App\Models\User;
use Illuminate\View\View;

/**
 * « Admins et accès » (super admin) : les comptes du back-office, à part des comptes
 * participants. Niveaux, activation, dernière action notée au journal.
 */
class AdminsController extends Controller
{
    public function index(): View
    {
        $admins = User::with('admin')->has('admin')->orderBy('nom')->orderBy('prenom')->get();
        $dernieres = JournalAdmin::whereIn('id_admin', $admins->pluck('id_user'))
            ->selectRaw('id_admin, max(created_at) as derniere')
            ->groupBy('id_admin')
            ->pluck('derniere', 'id_admin');

        return view('admin.admins.index', ['admins' => $admins, 'dernieres' => $dernieres]);
    }
}
