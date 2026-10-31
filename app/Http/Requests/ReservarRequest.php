<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $documento = $this->input('documento');
        $nombre = $this->input('nombre');
        $email = $this->input('email');

        $this->merge([
            'documento' => is_string($documento) ? strtoupper(str_replace([' ', '.'], '', trim($documento))) : $documento,
            'nombre' => is_string($nombre) ? preg_replace('/\s+/u', ' ', trim($nombre)) : $nombre,
            'email' => is_string($email) ? strtolower(trim($email)) : $email,
        ]);
    }

    public function rules(): array
    {
        return [
            'asientos' => ['required', 'array', 'min:1', 'max:'.(int) config('teatro.max_asientos_por_orden')],
            'asientos.*' => ['required', 'integer', 'min:1', 'distinct'],
            'nombre' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL\\pM' .-]+$/u"],
            'documento' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9-]{3,18}[A-Z0-9]$/'],
            'email' => ['required', 'string', 'max:190', 'email:rfc'],
        ];
    }

    public function messages(): array
    {
        return [
            'asientos.required' => 'Elija al menos un asiento en el plano.',
            'asientos.max' => 'Puede reservar como máximo :max asientos por compra.',
            'nombre.regex' => 'El nombre solo puede contener letras, espacios, puntos, apóstrofos y guiones.',
            'documento.regex' => 'El documento debe tener entre 5 y 20 caracteres: letras sin acentos, números y guiones.',
        ];
    }

    public function attributes(): array
    {
        return [
            'asientos' => 'los asientos',
            'nombre' => 'el nombre completo',
            'documento' => 'el documento de identidad',
            'email' => 'el correo electrónico',
        ];
    }
}
