<?php
require_once __DIR__ . '/../../src/ProcessarFiscalPortoSeguro.php';
require_once __DIR__ . '/../../NfseService.php';

// Exemplo de JSON de entrada
$jsonData = [
    'lote_id' => 'Lote260119073453362',
    'numeroLote' => '260119073453362',
    'cnpjPrestador' => '48909178000186',
    'inscricaoMunicipal' => '199122001',
    'quantidadeRps' => 1,
    'rps' => [
        [
            'inf_id' => 'A3044892601190734532',
            'infRps' => [
                'numero' => 304,
                'serie' => 'A',
                'tipo' => 1,
                'dataEmissao' => '2026-01-19T07:34:53-03:00'
            ],
            'competencia' => '20260101',
            'valorServicos' => 3.5,
            'valorIss' => 0,
            'aliquota' => 2,
            'issRetido' => 2,
            'itemListaServico' => '1401',
            'discriminacao' => 'SERVIÇOS PRESTADOS NA O.S.  VEÍCULO: [XPTO] - Ford KA 1.0 Flex 12V 5p',
            'codigoMunicipio' => '2925303',
            'exigibilidadeISS' => '1',
            'regimeEspecialTributacao' => 6,
            'optanteSimplesNacional' => 1,
            'incentivoFiscal' => 2,
            'codigoCnae' => '4520007',
            'codigoTributacaoMunicipio' => '1401',
            'municipioIncidencia' => '2925303',
            'tomador' => [
                'cpfCnpj' => '57219214553',
                'razaoSocial' => 'Rubens dos Santos',
                'endereco' => [
                    'logradouro' => 'Rua Porto Seguro',
                    'numero' => '12',
                    'bairro' => 'Centro',
                    'codigoMunicipio' => '2925303',
                    'uf' => 'BA',
                    'cep' => '45810000'
                ],
                'telefone' => '71996758056',
                'email' => 'salvadorbba@gmail.com'
            ]
        ]
    ]
];

// Configurações do NfseService
$wsdlPath = __DIR__ . '/../../documetos/porto-seguro/NFS-e.wsdl';
$certPath = 'caminho/para/certificado.pfx';
$certPassword = 'senha_do_certificado';

try {
        // Criar instância do ProcessarFiscalPortoSeguro
        $processador = new ProcessarFiscalPortoSeguro(
            $jsonData,
            $certPath,
            $certPassword,
            $wsdlPath
        );
    
    // Processar o JSON e enviar para o webservice
    $resultado = $processador->processar();
    
    echo "Processamento concluído com sucesso!\n";
    print_r($resultado);
} catch (\Exception $e) {
    echo "Erro ao processar NFSe: " . $e->getMessage() . "\n";
}