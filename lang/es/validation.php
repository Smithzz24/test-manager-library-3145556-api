<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',
    'in' => 'El valor de :attribute no está permitido.',
    'regex' => 'El formato de :attribute no es válido.',
    'prohibited' => 'El campo :attribute no se puede enviar en esta operación.',
    'array' => 'El campo :attribute debe ser una lista.',
    'date_format' => 'El campo :attribute debe tener el formato :format.',
    'min' => ['numeric' => 'El campo :attribute debe ser al menos :min.', 'array' => 'El campo :attribute debe contener al menos :min elemento.'],
    'max' => ['string' => 'El campo :attribute no puede superar :max caracteres.', 'array' => 'El campo :attribute no puede superar :max elementos.'],
    'between' => ['numeric' => 'El campo :attribute debe estar entre :min y :max.'],
];
