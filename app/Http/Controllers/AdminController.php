<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Small admin screens: categories, document types, users, audit log, settings, OCR queue. */
class AdminController extends Controller
{
    public function categories()
    {
        return view('admin.categories', ['categories' => DocumentCategory::withCount('documents')->orderBy('name')->get()]);
    }

    public function categoryStore(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100|unique:document_categories,name']);
        DocumentCategory::create($d);
        return back()->with('success', 'Category added.');
    }

    public function categoryUpdate(Request $r, DocumentCategory $category)
    {
        $d = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('document_categories', 'name')->ignore($category->id)]]);
        $category->update($d);
        return back()->with('success', 'Category updated.');
    }

    public function categoryDestroy(DocumentCategory $category)
    {
        if ($category->documents()->exists()) {
            return back()->with('error', 'This category is used by documents and cannot be deleted.');
        }
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    public function types()
    {
        return view('admin.types', [
            'types' => DocumentType::with('category')->orderBy('document_category_id')->orderBy('name')->get(),
            'categories' => DocumentCategory::orderBy('name')->get(),
        ]);
    }

    public function typeStore(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'document_category_id' => ['required', 'exists:document_categories,id',
                Rule::unique('document_types')->where('name', $r->input('name'))],
        ]);
        DocumentType::create($d);
        return back()->with('success', 'Document type added.');
    }

    public function typeDestroy(DocumentType $type)
    {
        if (Document::where('document_type_id', $type->id)->exists()) {
            return back()->with('error', 'This type is used by documents and cannot be deleted.');
        }
        $type->delete();
        return back()->with('success', 'Document type deleted.');
    }

    public function users()
    {
        return view('admin.users', ['users' => User::orderBy('name')->get()]);
    }

    public function userStore(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:255', 'username' => 'required|string|max:100|unique:users,username',
            'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'manager', 'viewer'])],
        ]);
        User::create($d + ['can_export' => $r->boolean('can_export'), 'is_active' => true]);
        return back()->with('success', 'User created.');
    }

    public function userUpdate(Request $r, User $user)
    {
        $d = $r->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => ['required', Rule::in(['admin', 'manager', 'viewer'])],
        ]);
        if (blank($d['password'])) {
            unset($d['password']);
        }
        $self = $user->id === $r->user()->id;   // never lock yourself out
        $user->update($d + [
            'can_export' => $r->boolean('can_export'),
            'is_active' => $self ? true : $r->boolean('is_active'),
        ]);
        if ($self && $d['role'] !== 'admin') {
            $user->update(['role' => 'admin']);
        }
        return back()->with('success', 'User updated.');
    }

    public function audit(Request $r)
    {
        $q = AuditLog::with('user')->latest('id');
        if ($r->filled('action')) {
            $q->where('action', $r->input('action'));
        }
        if ($r->filled('user')) {
            $q->where('user_id', $r->input('user'));
        }
        return view('admin.audit', [
            'logs' => $q->limit(5000)->get(),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function settings()
    {
        return view('admin.settings', [
            'defs' => \App\Models\Setting::DEFS,
            'values' => collect(\App\Models\Setting::DEFS)->map(fn ($d, $k) => \App\Models\Setting::current($k))->all(),
            'defaults' => \App\Models\Setting::defaults(),
            'phpUpload' => min(\App\Models\Setting::iniMb('upload_max_filesize'), \App\Models\Setting::iniMb('post_max_size')),
        ]);
    }

    public function settingsUpdate(Request $r)
    {
        $rules = [];
        foreach (\App\Models\Setting::DEFS as $key => $d) {
            $rules[$key] = ['required', $d['type'] === 'float' ? 'numeric' : 'integer', 'min:' . $d['min'], 'max:' . $d['max']];
        }
        $data = $r->validate($rules);
        \App\Models\Setting::store($data);
        \App\Services\AuditService::log('SETTINGS_UPDATE', null, null, 'Settings changed: ' . json_encode($data));
        return back()->with('success', 'Settings saved. They apply immediately to new uploads and OCR runs.');
    }

    public function settingsReset()
    {
        \App\Models\Setting::resetAll();
        \App\Services\AuditService::log('SETTINGS_UPDATE', null, null, 'Settings reset to defaults');
        return back()->with('success', 'Settings reset to the .env / default values.');
    }

    public function ocr()
    {
        return view('admin.ocr', [
            'documents' => $documents = Document::whereIn('ocr_status', ['pending', 'processing', 'failed'])
    ->orderByRaw("
        CASE ocr_status
            WHEN 'failed' THEN 1
            WHEN 'processing' THEN 2
            WHEN 'pending' THEN 3
            WHEN 'completed_warnings' THEN 4
            ELSE 5
        END
    ")
    ->limit(200)
    ->get(),
        ]);
    }
}
