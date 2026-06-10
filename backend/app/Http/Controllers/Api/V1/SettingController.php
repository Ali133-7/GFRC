<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class SettingController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->authorize('manage-settings', Setting::class);
        $settings = Setting::orderBy('group')->orderBy('label_ar')->get();
        return $this->success($settings->map(fn ($s) => [
            'id' => $s->id,
            'key' => $s->key,
            'value' => $s->value,
            'type' => $s->type,
            'group' => $s->group,
            'label_ar' => $s->label_ar,
            'description' => $s->description,
            'is_public' => $s->is_public,
        ]));
    }

    public function publicSettings(): JsonResponse
    {
        $settings = Setting::where('is_public', true)->get();
        return $this->success($settings->mapWithKeys(fn ($s) => [$s->key => $s->getTypedValue()]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage-settings', Setting::class);
        $data = $request->validate([
            'key' => 'required|string|unique:settings,key',
            'value' => 'nullable|string',
            'type' => 'in:string,number,boolean,json',
            'group' => 'string|max:50',
            'label_ar' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $data['id'] = (string) Str::uuid();
        $setting = Setting::create($data);

        $this->logSettingChange($setting, 'created');
        return $this->success($setting, 'تم إضافة الإعداد بنجاح');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage-settings', Setting::class);
        $setting = Setting::findOrFail($id);
        $oldValue = $setting->value;

        $data = $request->validate([
            'value' => 'nullable|string',
            'type' => 'in:string,number,boolean,json',
            'group' => 'string|max:50',
            'label_ar' => 'string|max:200',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $setting->update($data);

        if (array_key_exists('value', $data) && $oldValue !== $data['value']) {
            $this->logSettingChange($setting, 'updated', $oldValue, $data['value']);
        }

        return $this->success($setting, 'تم تحديث الإعداد بنجاح');
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('manage-settings', Setting::class);
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:100',
            'settings.*.value' => 'nullable|string',
        ]);

        foreach ($data['settings'] as $item) {
            $setting = Setting::where('key', $item['key'])->first();
            if ($setting) {
                $old = $setting->value;
                $setting->update(['value' => $item['value']]);
                if ($old !== $item['value']) {
                    $this->logSettingChange($setting, 'updated', $old, $item['value']);
                }
            } else {
                // Create setting if it doesn't exist
                $setting = Setting::create([
                    'id' => (string) Str::uuid(),
                    'key' => $item['key'],
                    'value' => $item['value'],
                    'type' => 'string',
                    'group' => 'general',
                    'label_ar' => $item['key'],
                    'is_public' => false,
                ]);
                $this->logSettingChange($setting, 'created', null, $item['value']);
            }
        }

        return $this->success([], 'تم تحديث الإعدادات بنجاح');
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('manage-settings', Setting::class);
        $setting = Setting::findOrFail($id);
        $this->logSettingChange($setting, 'deleted', $setting->value);
        $setting->delete();
        return $this->success([], 'تم حذف الإعداد بنجاح');
    }

    protected function logSettingChange(Setting $setting, string $event, mixed $old = null, mixed $new = null): void
    {
        activity()
            ->performedOn($setting)
            ->causedBy(auth()->user())
            ->withProperties([
                'old' => ['value' => $old],
                'new' => ['value' => $new],
                'key' => $setting->key,
            ])
            ->event($event)
            ->tap(function ($activity) {
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log("setting_{$event}");
    }
}
