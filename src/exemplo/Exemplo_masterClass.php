<?php
//require_once __DIR__ . '/../src/MasterClass.php'; ou   use NFSePrefeitura\NFSe\MasterClass;

// Instanciando a classe
$master = new MasterClass();

// Gerar número RPS
$rps = $master->gerarNumeroRps('A', 456, '12345678000199');
echo "Número RPS: $rps\n";

// Remover acentos
$texto = $master->removerAcentos('José da Silva');
echo "Texto sem acentos: $texto\n";

// Formatar moeda
$valorFormatado = $master->MoedaNF('1234.56');
echo "Valor formatado: $valorFormatado\n";

// Calcular percentual de desconto
$percentual = $master->calcDescPercentual(200, 20); // 10%
echo "Percentual de desconto: $percentual%\n";

// Calcular percentual de lucro
$lucro = $master->descPercentual('100', '120'); // 20%
echo "Percentual de lucro: $lucro%\n";

// Calcular percentual de diferença
$dif = $master->diferencaPercentual('200', '50'); // 25
echo "Percentual de diferença: $dif%\n";

// Normalizar documento
$doc = $master->TrataDoc('12.345.678/0001-99');
echo "Documento normalizado: $doc\n";
