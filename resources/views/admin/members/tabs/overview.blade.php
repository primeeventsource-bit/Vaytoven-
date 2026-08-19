<div class="vyt-row-2">
    <div class="vyt-card">
        <div class="vyt-card-header"><h3>Account</h3></div>
        <div class="vyt-card-body">
            <ul class="vyt-kv">
                <li><span class="k">Name</span><span class="v">{{ $member->name }}</span></li>
                <li><span class="k">Email</span><span class="v">{{ $member->email }}</span></li>
                <li><span class="k">Phone</span><span class="v">{{ $member->phone ?: '—' }}</span></li>
                <li><span class="k">Member ID</span><span class="v vyt-mono">{{ $member->id }}</span></li>
                <li><span class="k">Role</span><span class="v">{{ $member->role?->value ?? '—' }}</span></li>
                <li><span class="k">Created</span><span class="v">{{ et($member->created_at, 'M j, Y g:ia') }}</span></li>
                <li><span class="k">Last sign-in</span><span class="v">{{ $member->last_login_at?->diffForHumans() ?? 'never' }}</span></li>
                <li>
                    <span class="k">Password</span>
                    <span class="v">
                        @if ($member->must_change_password)
                            <span style="color:#92400e;">staff-issued, not yet replaced</span>
                        @elseif ($member->password_changed_at)
                            set by the member {{ $member->password_changed_at->diffForHumans() }}
                        @else
                            set by the member
                        @endif
                    </span>
                </li>
            </ul>
        </div>
    </div>

    <div class="vyt-card">
        <div class="vyt-card-header"><h3>Package</h3></div>
        <div class="vyt-card-body">
            @if ($package)
                <ul class="vyt-kv">
                    <li><span class="k">Package</span><span class="v">{{ $package->package->label() }}</span></li>
                    <li><span class="k">Weeks</span><span class="v">{{ $package->weeks }}</span></li>
                    <li><span class="k">Rate</span><span class="v">${{ $package->pricePerWeekDollars() }}/week</span></li>
                    <li><span class="k">Paid</span><span class="v">${{ $package->totalDollars() }}</span></li>
                    <li><span class="k">Order</span><span class="v vyt-mono">{{ $package->reference }}</span></li>
                    <li><span class="k">Paid on</span><span class="v">{{ et($package->paid_at, 'M j, Y') }}</span></li>
                    <li>
                        <span class="k">Properties allowed</span>
                        <span class="v">
                            {{ $package->package->propertyCount() }}
                            @if ($properties->count() > $package->package->propertyCount())
                                <span style="color:#b91c1c;">· {{ $properties->count() }} in use, over allowance</span>
                            @endif
                        </span>
                    </li>
                </ul>
            @else
                <p class="vyt-faint" style="margin:0;">
                    No paid Member Services package.
                    @if ($orders->isNotEmpty())
                        {{ $orders->count() }} unpaid {{ Str::plural('order', $orders->count()) }} on file — see Payments.
                    @endif
                </p>
            @endif
        </div>
    </div>
</div>

<div class="vyt-card" style="margin-top:18px;">
    <div class="vyt-card-header"><h3>Staff notes</h3></div>
    <div class="vyt-card-body">
        <form method="POST" action="{{ route('admin.members.notes', $member) }}">
            @csrf
            <textarea name="staff_notes" rows="4" style="width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:9px;font:inherit;"
                      placeholder="What should the next person picking up this account know?">{{ old('staff_notes', $member->staff_notes) }}</textarea>
            <button type="submit" class="site-cta" style="margin-top:12px;padding:9px 20px;font-size:14px;">Save notes</button>
        </form>
        <p class="site-note" style="margin-top:12px;">
            Notes are a current summary, not a record. Anything that needs authorship
            and chronology is in the Activity log, which cannot be edited.
        </p>
    </div>
</div>
