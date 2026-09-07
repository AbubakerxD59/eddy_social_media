<?php

namespace App\Http\Requests;

use App\Models\Signal;

class UpdateSignalRequest extends StoreSignalRequest
{
    public function authorize(): bool
    {
        $signal = $this->route('signal');

        return $this->user() !== null
            && $signal instanceof Signal
            && $signal->user_id === $this->user()->id;
    }

    protected function prepareForValidation(): void
    {
        $signal = $this->route('signal');

        if ($signal instanceof Signal) {
            $this->merge([
                'type' => $signal->type->value,
                'parent_id' => $signal->parent?->public_id,
            ]);
        }

        parent::prepareForValidation();
    }

    protected function existingMediaCountsForDrop(): bool
    {
        $signal = $this->route('signal');

        return $signal instanceof Signal && $signal->media()->exists();
    }
}
