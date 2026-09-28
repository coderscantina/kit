<?php

declare(strict_types=1);

namespace App\Http\Controllers\Records;

use App\Data\AttachmentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Records\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Concerns\HasAttachments;
use App\Models\User;
use App\Services\Attachments\AttachmentStorage;
use App\Support\Records;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploads are REST (a multipart body the reactive layer does not carry);
 * the list is the `attachments.list` query, which the upload and the delete
 * wake like any other write.
 */
class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentStorage $attachments,
    ) {}

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $record = Records::authorize(Gate::forUser($user), 'update', $request->string('type')->toString(), $request->string('id')->toString(), HasAttachments::class);
        abort_if($record === null, 404);

        $attachment = $this->attachments->store($record, $request->file('file'), $user);

        return response()->json(AttachmentData::fromModel($attachment->load('uploader')), 201);
    }

    public function show(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $this->attachments->response($attachment);
    }

    public function thumbnail(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $this->attachments->response($attachment, thumbnail: true);
    }

    public function destroy(Attachment $attachment): Response
    {
        Gate::authorize('delete', $attachment);

        $this->attachments->delete($attachment);

        return response()->noContent();
    }
}
