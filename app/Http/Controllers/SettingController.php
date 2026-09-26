<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPhoneNumberException;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\ApplicationSetting;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function show(): View
    {
        $this->authorize('view', ApplicationSetting::current());

        return view('settings.show');
    }

    public function update(UpdateSettingRequest $request, PhoneNumberService $phoneNumbers): RedirectResponse
    {
        $data = $request->validated();

        // An empty string, not null, is what a cleared field submits as —
        // the column's CHECK constraint only auto-passes on a real NULL.
        if ($data['phone'] === '') {
            $data['phone'] = null;
        } elseif ($data['phone']) {
            try {
                $data['phone'] = $phoneNumbers->canonicalize($data['phone']);
            } catch (InvalidPhoneNumberException $e) {
                throw ValidationException::withMessages(['phone' => $e->getMessage()]);
            }
        }

        ApplicationSetting::current()->update($data);

        return redirect()->route('settings.show')->with('status', 'Settings updated successfully.');
    }
}
