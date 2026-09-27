@props(['name'])

{{--
    Named wrapper over Breeze's <x-input-error>, so each invalid field shows its
    own message instead of one generic toast naming only the first error.

    Usage: <x-field-error name="fieldname" /> right after the input, plus
        @error('fieldname') border-red-500 @enderror
    on the input's class list so the border reflects the error too.
--}}
<x-input-error :messages="$errors->get($name)" class="mt-1" />
