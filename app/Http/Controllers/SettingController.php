<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function show(): View
    {
        $this->authorize('view', ApplicationSetting::current());

        return view('settings.show');
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        ApplicationSetting::current()->update($request->validated());

        return redirect()->route('settings.show')->with('status', 'Settings updated successfully.');
    }
}
