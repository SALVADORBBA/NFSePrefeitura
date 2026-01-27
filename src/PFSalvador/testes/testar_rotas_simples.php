<?php

/**
 * Teste simples e rápido das rotas da NFS-e Salvador-BA
 * Execute: php testar_rotas_simples.php
 */

echo "🧪 TESTE RÁPIDO DAS ROTAS NFS-e SALVADOR-BA\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// URLs dos webservices
$urls = [
    'homologacao' => 'https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl',
    'producao' => 'https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl'
];

foreach ($urls as $ambiente => $url) {
    echo "📡 Testando: " . strtoupper($ambiente) . "\n";
    echo "🔗 URL: {$url}\n";
    
    try {
        $inicio = microtime(true);
        
        // Testar conexão HTTP básica
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'header' => 'User-Agent: PHP-Test-Client'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        $fim = microtime(true);
        
        $tempo = round(($fim - $inicio) * 1000, 2);
        
        if ($response !== false) {
            echo "✅ CONEXÃO OK! ({$tempo}ms)\n";
            
            // Verificar se é um WSDL válido
            if (strpos($response, '<wsdl:definitions') !== false || 
                strpos($response, '<definitions') !== false) {
                echo "✅ WSDL VÁLIDO DETECTADO!\n";
            } else {
                echo "⚠️  Resposta recebida mas não parece ser WSDL\n";
            }
            
        } else {
            echo "❌ FALHA NA CONEXÃO\n";
        }
        
    } catch (Exception $e) {
        echo "❌ ERRO: " . $e->getMessage() . "\n";
    }
    
    echo str_repeat("-", 50) . "\n\n";
}

echo "💡 DICAS PARA TESTES MAIS COMPLETOS:\n";
echo "• Execute: php TestarRotasSalvador.php (teste detalhado)\n";
echo "• Execute: php TestarFuncoesSOAP.php (lista todas as funções)\n";
echo "• Para testar com certificado: php TestarRotasSalvador.php /caminho/cert.pfx senha\n";
echo "\n✅ Teste rápido finalizado!\n";