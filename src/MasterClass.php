<?php

namespace NFSePrefeitura\NFSe;

 
class MasterClass
{
    
     private function gerarNumeroRps(
    string $serie,
    int $numero,
    string $cnpj
): string {
    // Remove qualquer coisa que não seja número
    $cnpj = preg_replace('/\D/', '', $cnpj);

    // Prefixo do CNPJ (3 dígitos para economizar espaço)
    $cnpjPrefixo = substr($cnpj, 0, 3);

    // Timestamp compacto: yymmddhhmmss (12 chars)
    $timestamp = date('ymdHis');

    // Aleatório (2 chars)
    $aleatorio = random_int(10, 99);

    // Montagem
    $rps = $serie
         . str_pad($numero, 3, '0', STR_PAD_LEFT)
         . $cnpjPrefixo
         . $timestamp
         . $aleatorio;

    // Garante no máximo 20 caracteres
    return substr($rps, 0, 20);
}
    
}