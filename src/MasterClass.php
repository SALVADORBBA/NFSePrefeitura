<?php

namespace NFSePrefeitura\NFSe;

 
class MasterClass
{
    
     public function gerarNumeroRps(
    string $serie,
    int $numero,
    string $cnpj
) {
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




    public  function removerAcentos($texto)
    {
        // Remove acentos
        $texto = preg_replace('/[áàâãªä]/ui', 'a', $texto);
        $texto = preg_replace('/[éèêë]/ui', 'e', $texto);
        $texto = preg_replace('/[íìîï]/ui', 'i', $texto);
        $texto = preg_replace('/[óòôõö]/ui', 'o', $texto);
        $texto = preg_replace('/[úùûü]/ui', 'u', $texto);
        $texto = preg_replace('/[ç]/ui', 'c', $texto);

        // Remove caracteres especiais
        $texto = preg_replace('/[^!-ÿ]/u', ' ', $texto);

        // Remove espaços extras
        $texto = preg_replace('/\s+/', ' ', $texto);

        // Garante que o texto tenha pelo menos um caractere válido
        $texto = trim($texto);
        if (strlen($texto) < 1) {
            $texto = 'N/A'; // Valor padrão em caso de string vazia
        }

        return $texto;
    }

    public  function MoedaNF($valor)
    {
        // Remover pontos e substituir vírgulas por pontos
        $valor = str_replace('.', '', $valor); // Remove separador de milhares
        $valor = str_replace(',', '.', $valor); // Troca vírgula decimal por ponto decimal

        // Converter para float
        $valor = floatval($valor);

        // Truncar o valor para duas casas decimais sem arredondar
        $valor_truncado = floor($valor * 100) / 100;

        // Retornar o valor formatado com duas casas decimais
        return number_format($valor_truncado, 2, ',', '.');
    }
    
public function calcDescPercentual(float $total, float $desconto): float
{
    if ($total <= 0 || $desconto < 0 || $desconto > $total) {
        return 0;
    }

    return round(($desconto / $total) * 100, 2);
}


public function descPercentual($valor1, $valor2)
{
    // Normaliza valores (1.234,56 → 1234.56)
    $valor1 = str_replace(',', '.', str_replace('.', '', $valor1));
    $valor2 = str_replace(',', '.', str_replace('.', '', $valor2));

    $valor1 = (float) $valor1;
    $valor2 = (float) $valor2;

    // Validações
    if ($valor1 <= 0) {
        return 'Erro: Valor base inválido.';
    }

    // Cálculo do lucro
    $lucro = $valor2 - $valor1;

    // Percentual de lucro
    $percentual = ($lucro / $valor1) * 100;

    // Retorno formatado
    return number_format($percentual, 2, ',', '.') . '%';
}

public function diferencaPercentual($total, $valor)
{
    // Normaliza valores
    $total = str_replace(',', '.', str_replace('.', '', $total));
    $valor = str_replace(',', '.', str_replace('.', '', $valor));

    $total = (float) $total;
    $valor = (float) $valor;

    if ($total <= 0 || $valor < 0) {
        return 0;
    }

    $percentual = ($valor / $total) * 100;

    return round($percentual, 2);
}
  public function TrataDoc(?string $valor): string
{
    if (is_null($valor)) {
        return '';
    }

    return str_replace(['+', '.', '-', '/', '(', ')', ' '], '', $valor);
}
    
}