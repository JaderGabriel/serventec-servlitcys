<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserProfilePhotoService
{
    public function store(User $user, UploadedFile $file): string
    {
        $this->deleteFile($user);

        $extension = strtolower($file->extension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $filename = (string) $user->id.'_'.Str::random(8).'.'.$extension;

        return $file->storeAs('avatars', $filename, 'public');
    }

    public function delete(User $user): void
    {
        $this->deleteFile($user);
        $user->forceFill(['profile_photo_path' => null])->save();
    }

    /** Remove ficheiro em disco (ex.: antes de apagar a conta). */
    public function purge(User $user): void
    {
        $this->deleteFile($user);
    }

    private function deleteFile(User $user): void
    {
        $path = $user->profile_photo_path;
        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
