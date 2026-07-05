<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Services\AdminAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Email + SMS template manager. Versions are immutable: "saving" a template
 * inserts (key, version+1) and moves the active pointer — a used version is
 * never edited, so sent mail can always be traced to its exact copy.
 */
class EmailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $channel = $request->query('channel', 'email') === 'sms' ? 'sms' : 'email';
        $model = $channel === 'sms' ? SmsTemplate::class : EmailTemplate::class;

        $templates = $model::query()
            ->orderBy('key')
            ->orderByDesc('version')
            ->get()
            ->groupBy('key');

        return view('admin.settings.templates', [
            'channel' => $channel,
            'templates' => $templates,
        ]);
    }

    /** Create a new version of a template key (or the first version of a new key). */
    public function store(Request $request): RedirectResponse
    {
        $channel = $request->input('channel') === 'sms' ? 'sms' : 'email';

        $data = $request->validate($channel === 'sms' ? [
            'key' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:128'],
            'body_text' => ['required', 'string', 'max:1600'],
        ] : [
            'key' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:128'],
            'subject' => ['required', 'string', 'max:255'],
            'body_markdown' => ['required', 'string', 'max:50000'],
        ]);

        $model = $channel === 'sms' ? SmsTemplate::class : EmailTemplate::class;

        $template = DB::transaction(function () use ($model, $data, $request) {
            $previous = $model::query()
                ->where('key', $data['key'])
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            // Retire the currently active version; the new one takes over.
            $model::query()->where('key', $data['key'])->where('active', true)->update(['active' => false]);

            return $model::create([
                ...$data,
                'version' => ($previous?->version ?? 0) + 1,
                'active' => true,
                'variables' => $previous?->variables, // documented merge tags carry forward
                'updated_by' => $request->user()->id,
            ]);
        });

        AdminAuditLogService::log(
            actor: $request->user(),
            action: 'template.update',
            subject: $template,
            payload: ['channel' => $channel, 'key' => $template->key, 'new_version' => $template->version],
            ipAddress: $request->ip(),
        );

        return redirect()->route('admin.settings.templates', ['channel' => $channel])
            ->with('success', "Template '{$template->key}' saved as version {$template->version}.");
    }
}
