<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExeatAttachmentController extends Controller
{
    // POST /api/exeat-requests/{id}/attachments
    public function store($id, Request $request)
    {
        $user = $request->user();
        $exeat = ExeatRequest::find($id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx',
            'description' => 'nullable|string',
        ]);
        $path = $request->file('file')->store('attachments');
        $attachment = Attachment::create([
            'exeat_request_id' => $exeat->id,
            'file_path' => $path,
            'description' => $validated['description'] ?? null,
            'uploaded_by' => $user->id,
        ]);
        Log::info('Attachment uploaded', ['exeat_id' => $exeat->id, 'attachment_id' => $attachment->id]);
        return response()->json(['message' => 'Attachment uploaded.', 'attachment' => $attachment], 201);
    }

    // GET /api/exeat-requests/{id}/attachments
    public function index($id)
    {
        $exeat = ExeatRequest::find($id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $attachments = Attachment::where('exeat_request_id', $exeat->id)->get();
        // Add download URLs
        $attachments->transform(function ($item) {
            $item->download_url = $item->file_path ? Storage::url($item->file_path) : null;
            return $item;
        });
        Log::info('Attachments listed', ['exeat_id' => $exeat->id, 'count' => $attachments->count()]);
        return response()->json(['attachments' => $attachments]);
    }
}
