<?php

use NFSePrefeitura\NFSe\PortoSeguro;

// Caminho e senha do certificado digital
$certPath = __DIR__ . '/certificado.pfx';
$certPass = 'SENHA_DO_CERTIFICADO';

// Dados do lote e do RPS (exemplo, ajuste conforme sua necessidade)
$dados = [
    'lote_id' => 'Lote1',
    'numeroLote' => '12345',
    'cnpjPrestador' => '12345678000199',
    'inscricaoMunicipal' => '123456',
    'quantidadeRps' => 1,
    'rps' => [
        [
            'inf_id' => 'Rps1',
            'infRps' => [
                'numero' => '1',
                'serie' => 'A',
                'tipo' => '1',
                'dataEmissao' => '2024-06-01T10:00:00',
            ],
            'competencia' => '2024-06-01',
            'valorServicos' => '100.00',
            'valorIss' => '5.00',
            'aliquota' => '0.05',
            'issRetido' => '2',
            'itemListaServico' => '1401',
            'discriminacao' => 'Serviço de exemplo',
            'codigoMunicipio' => '1234567',
            'exigibilidadeISS' => '1',
            'regimeEspecialTributacao' => '1',
            'optanteSimplesNacional' => '1',
            'incentivoFiscal' => '2',
            'tomador' => [
                'cpfCnpj' => '98765432000188',
                'inscricaoMunicipal' => '654321',
                'razaoSocial' => 'Empresa Tomadora',
                'endereco' => [
                    'logradouro' => 'Rua Exemplo',
                    'numero' => '100',
                    'bairro' => 'Centro',
                    'codigoMunicipio' => '1234567',
                    'uf' => 'SP',
                    'cep' => '12345000',
                ],
                'telefone' => '11999999999',
                'email' => 'contato@empresa.com',
            ],
        ],
    ],
];

// 1. Gerar o XML do lote RPS
$xmlLote = PortoSeguro::gerarXmlLoteRps($dados);

// 2. Assinar o XML (exemplo: função fictícia assinarXml, ajuste conforme sua implementação)
// $xmlLoteAssinado = assinarXml($xmlLote, $certPath, $certPass);
// Se você já tem a assinatura separada para RPS e Lote, use os parâmetros do método

// 3. Enviar o XML assinado para o webservice (exemplo genérico)
// $client = new SoapClient('URL_DO_WSDL', [
//     'local_cert' => $certPath,
//     'passphrase' => $certPass,
//     'trace' => 1,
//     'exceptions' => 1,
// ]);
// $params = [
//     'nfseCabecMsg' => '...cabeçalho...',
//     'nfseDadosMsg' => $xmlLoteAssinado,
// ];
// $response = $client->__soapCall('RecepcionarLoteRps', [$params]);

// Exibir o XML gerado para conferência
header('Content-Type: text/xml; charset=utf-8');
echo $xmlLote;