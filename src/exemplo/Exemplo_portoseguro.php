<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use NFSePrefeitura\NFSe\PortoSeguro;
use NFSePrefeitura\NFSe\NFSeSigner;

/**
 * Exemplo de uso para a cidade de Porto Seguro/BA
 */
$certPath     = __DIR__ . '/certificado/certificado_od.pfx';
$certPassword = 'certPassword';

$dadosLote = [
    'lote_id'            => 'Lote1',
    'numeroLote'         => 1,
    'cnpjPrestador'      => '12345678901112',
    'inscricaoMunicipal' => '1234678995',
    'quantidadeRps'      => 1,
    'rps' => [
        [
            'inf_id' => 'RPS1',
            'infRps' => [
                'numero'       => 1,
                'serie'        => 'A',
                'tipo'         => 1,
                'dataEmissao'  => '2026-01-17 14:45:15',
            ],
            'competencia'                  => '2026-01-01',
            'valorServicos'               => 35.00,
            'valorIss'                    => 2.00,
            'aliquota'                    => 2.0000,
            'issRetido'                   => 2,
            'itemListaServico'            => '071001',
            'discriminacao'               => 'Serviço de exemplo',
            'codigoMunicipio'             => '2925303',
            'exigibilidadeISS'            => 1,
            'regimeEspecialTributacao'    => 0,
            'optanteSimplesNacional'      => 1,
            'incentivoFiscal'             => 0,
            'tomador' => [
                'cpfCnpj'            => '57219214553',
                'inscricaoMunicipal' => '',
                'razaoSocial'        => 'Rubens dos Santos',
                'endereco' => [
                    'logradouro'       => 'Rua Porto Seguro',
                    'numero'           => '12',
                    'bairro'           => 'Centro',
                    'codigoMunicipio'  => '2925303',
                    'uf'               => 'BA',
                    'cep'              => '41720230',
                ],
                'telefone' => '71996758056',
                'email'    => 'teste@exemplo.com',
            ],
        ],
    ],
];

try {
    $nfse = new PortoSeguro($certPath, $certPassword);
    $xml  = $nfse->gerarXmlLoteRps($dadosLote);
    $xmlAssinado = NFSeSigner::sign($xml, $certPath, $certPassword);
    salvarXml('xml_portoseguro_assinado.xml', $xmlAssinado);
    echo "XML gerado, assinado e salvo com sucesso!";
} catch (Throwable $e) {
    echo "Erro ao gerar/assinar XML NFSe: " . $e->getMessage();
}

function salvarXml(string $nomeArquivo, string $conteudo): void
{
    $dir = __DIR__ . '/app/xml_nfse/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $nomeFinal = date('Ymd_His_') . $nomeArquivo;
    file_put_contents($dir . $nomeFinal, $conteudo);
}