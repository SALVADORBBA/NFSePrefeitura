<?php

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Script para testar e listar todas as funções SOAP disponíveis nos webservices de Salvador-BA
 */

class TestarFuncoesSOAP
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
     * Lista todas as funções SOAP disponíveis
     */
    public function listarFuncoes(string $ambiente = 'homologacao'): array
    {
        echo "🔍 Listando funções SOAP - {$this->configuracoes[$ambiente]['descricao']}\n";
        echo "📡 URL: {$this->configuracoes[$ambiente]['url']}\n\n";
        
        $resultado = [
            'ambiente' => $ambiente,
            'url' => $this->configuracoes[$ambiente]['url'],
            'funcoes' => [],
            'tipos' => [],
            'erro' => null
        ];
        
        try {
            $options = [
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'soap_version' => SOAP_1_1,
                'encoding' => 'UTF-8',
                'connection_timeout' => 30
            ];
            
            $client = new SoapClient($this->configuracoes[$ambiente]['url'], $options);
            
            // Obter funções
            $funcoes = $client->__getFunctions();
            $tipos = $client->__getTypes();
            
            echo "📋 FUNÇÕES DISPONÍVEIS (" . count($funcoes) . " encontradas):\n";
            echo "=" . str_repeat("=", 80) . "\n\n";
            
            foreach ($funcoes as $index => $funcao) {
                echo "[" . ($index + 1) . "] " . $funcao . "\n";
                
                // Parse da função para extrair informações
                if (preg_match('/^(\w+)\s+(\w+)\((.*)\)/', $funcao, $matches)) {
                    $resultado['funcoes'][] = [
                        'retorno' => $matches[1],
                        'nome' => $matches[2],
                        'parametros' => $matches[3],
                        'original' => $funcao
                    ];
                } else {
                    $resultado['funcoes'][] = [
                        'original' => $funcao
                    ];
                }
            }
            
            echo "\n\n🔧 TIPOS DE DADOS (" . count($tipos) . " encontrados):\n";
            echo "=" . str_repeat("=", 80) . "\n\n";
            
            foreach ($tipos as $index => $tipo) {
                echo "[" . ($index + 1) . "] " . $tipo . "\n";
                $resultado['tipos'][] = $tipo;
            }
            
            // Identificar funções principais de NFSe
            echo "\n\n🎯 FUNÇÕES PRINCIPAIS DE NFS-e IDENTIFICADAS:\n";
            echo "=" . str_repeat("=", 80) . "\n\n";
            
            $funcoesNFSe = [
                'RecepcionarLoteRps' => 'Receber lote de RPS',
                'ConsultarSituacaoLoteRps' => 'Consultar situação do lote',
                'ConsultarLoteRps' => 'Consultar lote processado',
                'CancelarNfse' => 'Cancelar NFSe',
                'ConsultarNfsePorRps' => 'Consultar NFSe por RPS',
                'ConsultarNfse' => 'Consultar NFSe',
                'GerarNfse' => 'Gerar NFSe',
                'SubstituirNfse' => 'Substituir NFSe'
            ];
            
            foreach ($funcoesNFSe as $funcaoEsperada => $descricao) {
                $encontrada = false;
                foreach ($resultado['funcoes'] as $funcao) {
                    if (isset($funcao['nome']) && stripos($funcao['nome'], $funcaoEsperada) !== false) {
                        echo "✅ {$funcaoEsperada}: {$descricao}\n";
                        echo "   Assinatura: {$funcao['original']}\n\n";
                        $encontrada = true;
                        break;
                    }
                }
                if (!$encontrada) {
                    echo "❌ {$funcaoEsperada}: {$descricao} (não encontrada)\n\n";
                }
            }
            
        } catch (Exception $e) {
            $resultado['erro'] = [
                'mensagem' => $e->getMessage(),
                'codigo' => $e->getCode(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine()
            ];
            
            echo "❌ Erro ao conectar: " . $e->getMessage() . "\n";
            echo "📝 Código: " . $e->getCode() . "\n";
        }
        
        return $resultado;
    }
    
    /**
     * Testa todas as rotas e compara
     */
    public function compararAmbientes(): array
    {
        echo "🔄 COMPARAÇÃO ENTRE AMBIENTES\n";
        echo "=" . str_repeat("=", 80) . "\n\n";
        
        $comparacao = [];
        
        foreach ($this->configuracoes as $ambiente => $config) {
            echo "📊 Analisando: {$config['descricao']}\n";
            echo str_repeat("-", 40) . "\n";
            
            $resultado = $this->listarFuncoes($ambiente);
            $comparacao[$ambiente] = $resultado;
            
            echo "\n" . str_repeat("-", 40) . "\n\n";
        }
        
        // Análise comparativa
        echo "📈 ANÁLISE COMPARATIVA\n";
        echo "=" . str_repeat("=", 80) . "\n\n";
        
        $funcoesHomologacao = array_column($comparacao['homologacao']['funcoes'], 'nome');
        $funcoesProducao = array_column($comparacao['producao']['funcoes'], 'nome');
        
        $funcoesComuns = array_intersect($funcoesHomologacao, $funcoesProducao);
        $funcoesSoHomologacao = array_diff($funcoesHomologacao, $funcoesProducao);
        $funcoesSoProducao = array_diff($funcoesProducao, $funcoesHomologacao);
        
        echo "✅ Funções comuns aos dois ambientes: " . count($funcoesComuns) . "\n";
        if (!empty($funcoesComuns)) {
            foreach ($funcoesComuns as $funcao) {
                echo "   • {$funcao}\n";
            }
        }
        
        echo "\n⚠️  Funções apenas em homologação: " . count($funcoesSoHomologacao) . "\n";
        if (!empty($funcoesSoHomologacao)) {
            foreach ($funcoesSoHomologacao as $funcao) {
                echo "   • {$funcao}\n";
            }
        }
        
        echo "\n⚠️  Funções apenas em produção: " . count($funcoesSoProducao) . "\n";
        if (!empty($funcoesSoProducao)) {
            foreach ($funcoesSoProducao as $funcao) {
                echo "   • {$funcao}\n";
            }
        }
        
        return $comparacao;
    }
    
    /**
     * Testa a latência de ambos os ambientes
     */
    public function testarLatencia(): array
    {
        echo "⚡ TESTE DE LATÊNCIA\n";
        echo "=" . str_repeat("=", 80) . "\n\n";
        
        $resultados = [];
        
        foreach ($this->configuracoes as $ambiente => $config) {
            echo "📍 Testando latência - {$config['descricao']}\n";
            
            $tempos = [];
            $totalTestes = 3;
            
            for ($i = 1; $i <= $totalTestes; $i++) {
                try {
                    $inicio = microtime(true);
                    
                    $options = [
                        'trace' => 1,
                        'exceptions' => true,
                        'cache_wsdl' => WSDL_CACHE_NONE,
                        'soap_version' => SOAP_1_1,
                        'encoding' => 'UTF-8',
                        'connection_timeout' => 10
                    ];
                    
                    $client = new SoapClient($config['url'], $options);
                    $funcoes = $client->__getFunctions();
                    
                    $fim = microtime(true);
                    $tempo = round(($fim - $inicio) * 1000, 2);
                    
                    $tempos[] = $tempo;
                    echo "  Teste {$i}: {$tempo}ms\n";
                    
                } catch (Exception $e) {
                    echo "  Teste {$i}: FALHA - " . $e->getMessage() . "\n";
                    $tempos[] = null;
                }
            }
            
            // Calcular média (apenas testes bem-sucedidos)
            $temposValidos = array_filter($tempos, function($t) { return $t !== null; });
            
            if (!empty($temposValidos)) {
                $media = round(array_sum($temposValidos) / count($temposValidos), 2);
                $min = min($temposValidos);
                $max = max($temposValidos);
                
                echo "  📊 Média: {$media}ms (min: {$min}ms, max: {$max}ms)\n";
                
                $resultados[$ambiente] = [
                    'media_ms' => $media,
                    'min_ms' => $min,
                    'max_ms' => $max,
                    'total_testes' => count($temposValidos),
                    'falhas' => count($tempos) - count($temposValidos)
                ];
            } else {
                echo "  ❌ Todos os testes falharam\n";
                $resultados[$ambiente] = [
                    'media_ms' => null,
                    'erro' => 'Todos os testes falharam'
                ];
            }
            
            echo "\n";
        }
        
        return $resultados;
    }
}

// Execução do script
if (php_sapi_name() === 'cli') {
    echo "🔍 ANALISADOR DE FUNÇÕES SOAP - SALVADOR-BA\n";
    echo "=" . str_repeat("=", 80) . "\n\n";
    
    $analisador = new TestarFuncoesSOAP();
    
    // Menu de opções
    echo "Escolha uma opção:\n";
    echo "1. Listar funções de homologação\n";
    echo "2. Listar funções de produção\n";
    echo "3. Comparar ambos os ambientes\n";
    echo "4. Testar latência\n";
    echo "5. Executar todos os testes\n\n";
    
    $opcao = $argv[1] ?? readline("Digite o número da opção (1-5): ");
    
    switch ($opcao) {
        case '1':
            $analisador->listarFuncoes('homologacao');
            break;
        case '2':
            $analisador->listarFuncoes('producao');
            break;
        case '3':
            $analisador->compararAmbientes();
            break;
        case '4':
            $analisador->testarLatencia();
            break;
        case '5':
            echo "🔍 FUNÇÕES DE HOMOLOGAÇÃO:\n";
            echo str_repeat("-", 40) . "\n";
            $analisador->listarFuncoes('homologacao');
            
            echo "\n\n🔍 FUNÇÕES DE PRODUÇÃO:\n";
            echo str_repeat("-", 40) . "\n";
            $analisador->listarFuncoes('producao');
            
            echo "\n\n📊 COMPARAÇÃO ENTRE AMBIENTES:\n";
            echo str_repeat("-", 40) . "\n";
            $analisador->compararAmbientes();
            
            echo "\n\n⚡ TESTE DE LATÊNCIA:\n";
            echo str_repeat("-", 40) . "\n";
            $analisador->testarLatencia();
            break;
        default:
            echo "❌ Opção inválida!\n";
            exit(1);
    }
    
    echo "\n✅ Análise finalizada!\n";
}