<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\Request;
use RuntimeException;

class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index()
    {
        return view('admin.backups', ['backups' => $this->backups->list()]);
    }

    public function store(Request $request)
    {
        try {
            $name = $this->backups->create($request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Backup made: {$name}.");
    }

    public function download(string $backup)
    {
        try {
            return response()->download($this->backups->path($backup));
        } catch (RuntimeException $e) {
            abort(404);
        }
    }

    public function restore(Request $request, string $backup)
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => 'That password is wrong, so nothing was restored.',
        ]);
        set_time_limit(600);

        try {
            $safety = $this->backups->restore($backup, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('backups.index')
            ->with('success', "Restored {$backup}. The data from just before is saved as {$safety}, in case you need it back.");
    }
}
