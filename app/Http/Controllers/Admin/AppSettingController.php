<?php

namespace App\Http\Controllers\Admin;

use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Branding (white-label) & pengaturan global. Logo disajikan lewat route,
 * bukan symlink storage, agar aman di shared hosting.
 */
class AppSettingController extends AdminController
{
    public function edit(SettingService $settings): View
    {
        $this->superAdminOnly();

        return view('settings.app', [
            'page' => 'app-settings',
            'title' => 'Pengaturan Aplikasi',
            's' => $settings->app(),
        ]);
    }

    public function update(Request $request, SettingService $settings): JsonResponse
    {
        $this->superAdminOnly();

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'company_name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', 'hex_color'],
            'device_binding' => ['required', 'boolean'],
            'min_app_version' => ['required', 'regex:/^\d+\.\d+\.\d+$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ], ['min_app_version.regex' => 'Format versi: 1.0.0'], [
            'app_name' => 'nama aplikasi', 'company_name' => 'nama perusahaan', 'min_app_version' => 'versi minimum',
        ]);

        $values = [
            'app_name' => $data['app_name'],
            'company_name' => $data['company_name'],
            'primary_color' => strtolower($data['primary_color']),
            'device_binding' => (bool) $data['device_binding'],
            'min_app_version' => $data['min_app_version'],
        ];

        $old = $settings->app('logo');
        if ($request->hasFile('logo')) {
            $values['logo'] = $request->file('logo')->store('branding');
        } elseif ($request->boolean('remove_logo')) {
            $values['logo'] = null;
        }
        if (array_key_exists('logo', $values) && $old) {
            Storage::delete($old);
        }

        $settings->setApp($values);

        return $this->saved('Pengaturan aplikasi disimpan.', ['reload' => true]);
    }

    public function logo(SettingService $settings): StreamedResponse
    {
        $path = $settings->app('logo');
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path, headers: ['Cache-Control' => 'public, max-age=3600']);
    }
}
