@php
    $validationControlId = $controlId ?? $field;
    $validationMessage = ($showErrors ?? true) ? $errors->first($field) : '';
@endphp

<div id="{{ $validationControlId }}-feedback"
     class="form-validation-message{{ $validationMessage ? ' is-visible' : '' }}"
     data-validation-feedback-for="{{ $validationControlId }}"
     aria-live="polite">{{ $validationMessage }}</div>
