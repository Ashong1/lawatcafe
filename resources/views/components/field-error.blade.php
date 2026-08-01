@props(['name'])

{{--
    Thin named wrapper over Breeze's own <x-input-error> — that component
    already exists and is already styled correctly, but was only ever
    consumed by the auth/profile scaffolding. Every custom-built feature
    form in this app relied entirely on a single generic toast showing
    $errors->first() (the FIRST error in the whole request, with no
    indication of which field it belongs to) — a form with two invalid
    fields told the user about exactly one of them.

    Usage: put <x-field-error name="fieldname" /> right after the input,
    and add this class-helper convention to the input's own class list so
    the border reflects the error too:
        @error('fieldname') border-red-500 @enderror
--}}
<x-input-error :messages="$errors->get($name)" class="mt-1" />
