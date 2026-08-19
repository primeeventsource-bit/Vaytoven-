<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Docs\StaffGuide;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads the staff training guide.
 *
 * Generated per request rather than served from a stored file, so the copy
 * somebody downloads describes the roles, permissions and packages configured
 * on this environment today. A guide checked in as an asset is accurate on the
 * day it is committed and quietly wrong afterwards, which is worse than absent
 * because staff act on it.
 *
 * Gated on being staff rather than on a specific permission: there is nothing
 * confidential in it beyond the shape of the admin area, and a new starter with
 * a narrow role is exactly who needs to read it.
 */
class StaffGuideController extends Controller
{
    public function __invoke(StaffGuide $guide): Response
    {
        abort_unless(request()->user()?->isStaff(), 403);

        return response($guide->render(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$guide->filename().'"',
        ]);
    }
}
