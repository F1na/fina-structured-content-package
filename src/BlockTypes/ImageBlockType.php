<?php

namespace Fina\StructuredContent\BlockTypes;

use Fina\StructuredContent\AbstractBlockType;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Generic block type registered by default by the package. Owns its file
 * directly — no shared media library: the file is uploaded as part of the
 * same request that creates/updates this specific block, and is deleted
 * along with it. Nothing outside this class or the "data" it persists knows
 * a file is involved.
 */
class ImageBlockType extends AbstractBlockType
{
    public static function key(): string
    {
        return 'image';
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'max:'.config('blocks.media_max_size', 10240)],
            'alt' => ['nullable', 'string'],
        ];
    }

    public function rulesForUpdate(): array
    {
        return [
            // Optional on update: omitting it keeps the currently stored file.
            'file' => ['sometimes', 'file', 'image', 'max:'.config('blocks.media_max_size', 10240)],
            'alt' => ['nullable', 'string'],
        ];
    }

    public function prepareForStorage(array $validated, Request $request, array $previousData): array
    {
        if (! $request->hasFile('file')) {
            return array_merge(Arr::only($previousData, ['disk', 'path', 'mimeType', 'size']), Arr::except($validated, ['file']));
        }

        if (! empty($previousData['path'])) {
            Storage::disk($previousData['disk'])->delete($previousData['path']);
        }

        $disk = config('blocks.media_disk', 'public');
        $file = $request->file('file');
        $path = $file->store('blocks/images', $disk);

        return array_merge(Arr::except($validated, ['file']), [
            'disk' => $disk,
            'path' => $path,
            'mimeType' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function afterDelete(array $data): void
    {
        if (! empty($data['path'])) {
            Storage::disk($data['disk'])->delete($data['path']);
        }
    }

    public function toArray(array $data): array
    {
        return [
            'url' => isset($data['path']) ? Storage::disk($data['disk'])->url($data['path']) : null,
            'alt' => $data['alt'] ?? null,
        ];
    }
}
