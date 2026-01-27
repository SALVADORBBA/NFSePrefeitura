<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use NFSePrefeitura\NFSe\PFSalvador\Salvador;

/**
 * Script para testar as rotas (webservices) da NFS-e de Salvador-BA
 * Este script testa a conectividade com os webservices de homologação e produção
 */

class TestarRotasSalvador
{
    private array $configuracoes;
    
    public function __construct()
    {
        $this->configuracoes = [
            'homologacao' => [
                'url' => 'https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl',
                'descricao' => 'Ambiente de Homologação'
            ],
            'producao' => [
                'url' => 'https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl',
                'descricao' => 'Ambiente de Produção'
            ]
        ];
    }
    
    /**
     * Testa a conectividade com o webservice
     */
    public function testarConectividade(string $ambiente = 'homologacao'): array
    {
        echo "🔍 Testando conectividade com {$this->configuracoes[$ambiente]['descricao']}...\n";
        echo "📡 URL: {$this->configuracoes[$ambiente]['url']}\n\n";
        
        $resultado = [
            'ambiente' => $ambiente,
            'url' => $this->configuracoes[$ambiente]['url'],
            'status' => 'error',
            'mensagem' => '',
            'detalhes' => []
        ];
        
        try {
            // Criar um SoapClient básico para testar a conexão
            $options = [
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'soap_version' => SOAP_1_1,
                'encoding' => 'UTF-8',
                'connection_timeout' => 30,
                'stream_context' => stream_context_create([
                    'http' => [
                        'timeout' => 30,
                        'user_agent' => 'PHP-SOAP-Test'
                    ]
                ])
            ];
            
            $inicio = microtime(true);
            $client = new SoapClient($this->configuracoes[$ambiente]['url'], $options);
            $fim = microtime(true);
            
            $tempoResposta = round(($fim - $inicio) * 1000, 2);
            
            // Obter funções disponíveis
            $funcoes = $client->__getFunctions();
            $tipos = $client->__getTypes();
            
            $resultado['status'] = 'success';
            $resultado['mensagem'] = "✅ Conexão estabelecida com sucesso!";
            $resultado['detalhes'] = [
                'tempo_resposta_ms' => $tempoResposta,
                'funcoes_disponiveis' => count($funcoes),
                'tipos_disponiveis' => count($tipos),
                'funcoes_principais' => array_slice($funcoes, 0, 5), // Primeiras 5 funções
                'versao_soap' => 'SOAP 1.1',
                'encoding' => 'UTF-8'
            ];
            
            echo "✅ Conexão estabelecida com sucesso!\n";
            echo "⏱️  Tempo de resposta: {$tempoResposta}ms\n";
            echo "📋 Funções disponíveis: " . count($funcoes) . "\n";
            echo "🔧 Tipos de dados: " . count($tipos) . "\n\n";
            
            // Listar funções principais
            echo "🔑 Principais funções disponíveis:\n";
            foreach (array_slice($funcoes, 0, 5) as $funcao) {
                echo "   • {$funcao}\n";
            }
            
        } catch (SoapFault $e) {
            $resultado['mensagem'] = "❌ Erro SOAP: " . $e->getMessage();
            $resultado['detalhes']['tipo_erro'] = 'SOAP_FAULT';
            $resultado['detalhes']['codigo_erro'] = $e->getCode();
            
            echo "❌ Erro SOAP: " . $e->getMessage() . "\n";
            echo "📝 Código do erro: " . $e->getCode() . "\n";
            
        } catch (Exception $e) {
            $resultado['mensagem'] = "❌ Erro geral: " . $e->getMessage();
            $resultado['detalhes']['tipo_erro'] = 'GENERAL_ERROR';
            $resultado['detalhes']['codigo_erro'] = $e->getCode();
            
            echo "❌ Erro geral: " . $e->getMessage() . "\n";
            echo "📝 Código do erro: " . $e->getCode() . "\n";
        }
        
        return $resultado;
    }
    
    /**
     * Testa todas as rotas
     */
    public function testarTodasRotas(): array
    {
        echo "🚀 Iniciando teste completo das rotas da NFS-e Salvador-BA\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $resultados = [];
        
        foreach ($this->configuracoes as $ambiente => $config) {
            echo "🔍 Testando: {$config['descricao']}\n";
            echo str_repeat("-", 40) . "\n";
            
            $resultado = $this->testarConectividade($ambiente);
            $resultados[$ambiente] = $resultado;
            
            echo "\n" . str_repeat("-", 40) . "\n\n";
        }
        
        // Resumo final
        echo "📊 RESUMO DOS TESTES\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        foreach ($resultados as $ambiente => $resultado) {
            $statusIcon = $resultado['status'] === 'success' ? '✅' : '❌';
            echo "{$statusIcon} {$resultado['url']} ({$resultado['ambiente']})\n";
            echo "   {$resultado['mensagem']}\n";
            
            if ($resultado['status'] === 'success' && isset($resultado['detalhes']['tempo_resposta_ms'])) {
                echo "   ⏱️  Tempo: {$resultado['detalhes']['tempo_resposta_ms']}ms\n";
            }
            echo "\n";
        }
        
        return $resultados;
    }
    
    /**
     * Testa com certificado digital (se disponível)
     */
    public function testarComCertificado(string $certPath, string $certPassword, string $ambiente = 'homologacao'): array
    {
        echo "🔐 Testando conexão com certificado digital...\n";
        echo "📡 Ambiente: {$this->configuracoes[$ambiente]['descricao']}\n\n";
        
        $resultado = [
            'status' => 'error',
            'mensagem' => '',
            'detalhes' => []
        ];
        
        try {
            // Testar com a classe Salvador real
            $salvador = new Salvador($certPath, $certPassword, $ambiente);
            
            // Criar XML de teste simples
            $xmlTeste = '<?xml version="1.0" encoding="UTF-8"?>';
            $xmlTeste .= '<ConsultarSituacaoLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
            $xmlTeste .= '<Prestador>';
            $xmlTeste .= '<Cnpj>00000000000191</Cnpj>'; // CNPJ de teste
            $xmlTeste .= '<InscricaoMunicipal>000000</InscricaoMunicipal>';
            $xmlTeste .= '</Prestador>';
            $xmlTeste .= '<Protocolo>TESTE123</Protocolo>';
            $xmlTeste .= '</ConsultarSituacaoLoteRpsEnvio>';
            
            echo "📤 Enviando requisição de teste...\n";
            
            try {
                $resposta = $salvador->consultarSituacaoLoteRps(
                    '00000000000191',
                    '000000',
                    'TESTE123'
                );
                
                $resultado['status'] = 'success';
                $resultado['mensagem'] = "✅ Conexão com certificado estabelecida!";
                $resultado['detalhes']['resposta_xml'] = $resposta;
                
                echo "✅ Conexão com certificado estabelecida!\n";
                echo "📄 Resposta recebida: " . substr($resposta, 0, 200) . "...\n";
                
            } catch (Exception $e) {
                // Erro esperado devido a dados de teste, mas conexão funcionou
                if (strpos($e->getMessage(), 'protocolo') !== false || 
                    strpos($e->getMessage(), 'Protocolo') !== false ||
                    strpos($e->getMessage(), 'não encontrado') !== false) {
                    
                    $resultado['status'] = 'success';
                    $resultado['mensagem'] = "✅ Conexão com certificado estabelecida! (Erro esperado: dados de teste)";
                    $resultado['detalhes']['erro_esperado'] = $e->getMessage();
                    
                    echo "✅ Conexão com certificado estabelecida!\n";
                    echo "⚠️  Erro esperado (dados de teste): " . $e->getMessage() . "\n";
                } else {
                    throw $e;
                }
            }
            
        } catch (Exception $e) {
            $resultado['mensagem'] = "❌ Erro com certificado: " . $e->getMessage();
            $resultado['detalhes']['tipo_erro'] = 'CERTIFICATE_ERROR';
            
            echo "❌ Erro com certificado: " . $e->getMessage() . "\n";
        }
        
        return $resultado;
    }
}

// Execução do script
if (php_sapi_name() === 'cli') {
    echo "🧪 TESTADOR DE ROTAS NFS-e SALVADOR-BA\n";
    echo "=" . str_repeat("=", 60) . "\n\n";
    
    $testador = new TestarRotasSalvador();
    
    // Teste básico (sem certificado)
    $resultados = $testador->testarTodasRotas();
    
    echo "\n💡 DICAS PARA TESTES COM CERTIFICADO:\n";
    echo "=" . str_repeat("=", 60) . "\n";
    echo "Para testar com certificado digital, execute:\n";
    echo "php " . basename(__FILE__) . " /caminho/certificado.pfx senha homologacao\n";
    echo "\nParâmetros:\n";
    echo "  1. Caminho do certificado (.pfx)\n";
    echo "  2. Senha do certificado\n";
    echo "  3. Ambiente (homologacao|producao)\n";
    
    // Teste com certificado se fornecido
    if ($argc >= 3) {
        echo "\n🔐 Testando com certificado digital...\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $certPath = $argv[1];
        $certPassword = $argv[2];
        $ambiente = $argv[3] ?? 'homologacao';
        
        if (!file_exists($certPath)) {
            echo "❌ Certificado não encontrado: {$certPath}\n";
            exit(1);
        }
        
        $testador->testarComCertificado($certPath, $certPassword, $ambiente);
    }
    
    echo "\n✅ Teste finalizado!\n";
}