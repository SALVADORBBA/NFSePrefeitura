<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use NFSePrefeitura\NFSe\PFSalvador\Salvador;
use NFSePrefeitura\NFSe\PFSalvador\SalvadorGeradorXML;

/**
 * Exemplo de uso do gerador de XML e transmissão para Salvador-BA
 */

// Dados de exemplo para geração do XML
$dadosLoteRPS = [
    'lote_id' => '12345',
    'numeroLote' => '2024000001',
    'cnpjPrestador' => '12345678000123',
    'inscricaoMunicipal' => '123456',
    'quantidadeRps' => '1',
    'rps' => [
        [
            'inf_id' => '123456789',
            'infRps' => [
                'numero' => '123456789',
                'serie' => '1',
                'tipo' => '1',
                'dataEmissao' => '2024-01-15T10:30:00',
            ],
            'competencia' => '2024-01-15',
            'naturezaOperacao' => '1',
            'optanteSimplesNacional' => '1',
            'incentivadorCultural' => '2',
            'status' => '1',
            'valorServicos' => '1000.00',
            'valorIss' => '50.00',
            'baseCalculo' => '1000.00',
            'aliquota' => '0.05',
            'issRetido' => '1',
            'itemListaServico' => '14.01',
            'codigoCnae' => '4520007',
            'discriminacao' => 'Serviços de construção civil',
            'codigoMunicipio' => '2927408', // Salvador-BA
            'prestador' => [
                'cnpj' => '12345678000123',
                'inscricaoMunicipal' => '123456',
            ],
            'tomador' => [
                'cnpj' => '98765432000198',
                'razaoSocial' => 'Empresa Tomadora Ltda',
                'endereco' => [
                    'logradouro' => 'Rua Teste',
                    'numero' => '123',
                    'bairro' => 'Centro',
                    'codigoMunicipio' => '2927408', // Salvador-BA
                    'uf' => 'BA',
                    'cep' => '40000000',
                ],
                'contato' => [
                    'telefone' => '7133333333',
                    'email' => 'contato@tomadora.com.br',
                ],
            ],
        ],
    ],
];

// Exemplo 1: Gerar XML apenas
try {
    echo "=== EXEMPLO 1: Geração de XML ===\n";
    $gerador = new SalvadorGeradorXML();
    $xmlGerado = $gerador->gerarXmlLoteRps($dadosLoteRPS);
    
    echo "XML Gerado:\n";
    echo $xmlGerado;
    echo "\n\n";
    
} catch (Exception $e) {
    echo "Erro na geração do XML: " . $e->getMessage() . "\n";
}

// Exemplo 2: Gerar, assinar e transmitir (quando houver certificado)
try {
    echo "=== EXEMPLO 2: Geração, Assinatura e Transmissão ===\n";
    
    // Configurações do certificado (ajustar conforme necessário)
    $certPath = '/caminho/para/certificado.pfx';
    $certPassword = 'senha_do_certificado';
    $ambiente = 'homologacao'; // ou 'producao'
    
    $salvador = new Salvador($certPath, $certPassword, $ambiente);
    
    // Processo completo
    $resultado = $salvador->gerarAssinarTransmitirLoteRps($dadosLoteRPS);
    
    echo "Resultado:\n";
    echo "XML Gerado: " . $resultado['xml_gerado'] . "\n";
    echo "XML Assinado: " . $resultado['xml_assinado'] . "\n";
    echo "XML Resposta: " . $resultado['xml_resposta'] . "\n";
    
} catch (Exception $e) {
    echo "Erro no processo completo: " . $e->getMessage() . "\n";
}

// Exemplo 3: Consultar situação de lote
try {
    echo "=== EXEMPLO 3: Consultar Situação de Lote ===\n";
    
    $certPath = '/caminho/para/certificado.pfx';
    $certPassword = 'senha_do_certificado';
    $ambiente = 'homologacao';
    
    $salvador = new Salvador($certPath, $certPassword, $ambiente);
    
    $cnpj = '12345678000123';
    $inscricaoMunicipal = '123456';
    $protocolo = '2024000001';
    
    $resposta = $salvador->consultarSituacaoLoteRps($cnpj, $inscricaoMunicipal, $protocolo);
    echo "Resposta da consulta: " . $resposta . "\n";
    
} catch (Exception $e) {
    echo "Erro na consulta: " . $e->getMessage() . "\n";
}

// Exemplo 4: Cancelar NFSe
try {
    echo "=== EXEMPLO 4: Cancelar NFSe ===\n";
    
    $certPath = '/caminho/para/certificado.pfx';
    $certPassword = 'senha_do_certificado';
    $ambiente = 'homologacao';
    
    $salvador = new Salvador($certPath, $certPassword, $ambiente);
    
    $cnpj = '12345678000123';
    $inscricaoMunicipal = '123456';
    $numeroNfse = '123456789';
    $codigoCancelamento = '1'; // 1-Erro de emissão, 2-Erro de serviço, 3-Outros
    $justificativa = 'Erro de digitação nos dados da nota fiscal';
    
    $resposta = $salvador->cancelarNfse($cnpj, $inscricaoMunicipal, $numeroNfse, $codigoCancelamento, $justificativa);
    echo "Resposta do cancelamento: " . $resposta . "\n";
    
} catch (Exception $e) {
    echo "Erro no cancelamento: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DOS EXEMPLOS ===\n";