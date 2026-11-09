<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadController extends Controller
{
    /**
     * رفع صورة (شعار شركة/فرع أو غلاف)
     * POST /upload
     * form-data: file, folder (companies|branches|users|items)
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file'   => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:4096',
                'folder' => 'nullable|in:companies,branches,users,items',
            ]);

            $folder = $request->input('folder', 'misc');
            $file   = $request->file('file');
            $name   = Str::uuid() . '.' . $file->getClientOriginalExtension();

            $path = $file->storeAs("uploads/{$folder}", $name, 'public');

            return response()->json([
                'success' => true,
                'message' => 'تم رفع الصورة',
                'data'    => [
                    'path' => $path,
                    'url'  => Storage::disk('public')->url($path),
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل رفع الصورة: ' . $e->getMessage(),
                'data'    => null,
                'code'    => 500,
            ], 500);
        }
    }

    /**
     * حذف صورة
     * DELETE /upload?path=uploads/companies/xxx.png
     */
    public function delete(Request $request)
    {
        try {
            $path = $request->input('path');
            if (!$path) {
                return response()->json(['success' => false, 'message' => 'المسار مطلوب', 'data' => null, 'code' => 422], 422);
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json(['success' => true, 'message' => 'تم حذف الصورة', 'data' => null, 'code' => 200], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }
}
