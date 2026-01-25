<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use NFSePrefeitura\NFSe\PFChapeco\AssinaturaChapeco;

/**
 * =====================================================
 * 🔐 CONFIGURAÇÃO DO CERTIFICADO
 * =====================================================
 */
$certPath     = __DIR__ . '/certificado/certificado_od.pfx';
$certPassword = '15021502';

/**
 * =====================================================
 * 📄 ARQUIVO XML A SER ASSINADO
 * =====================================================
 */
$xmlFile = __DIR__ . '/xml_gerado_debug.xml';
if (!file_exists($xmlFile)) {
    echo "Arquivo XML não encontrado: $xmlFile\n";
    exit(1);
}
$xmlConteudo = file_get_contents($xmlFile);

try {
    // Assinatura do XML usando AssinaturaChapeco
    $assinador = new AssinaturaChapeco($certPath, $certPassword);
    $xmlAssinado = $assinador->assinarLoteRps($xmlConteudo);

    // Salva o XML assinado
    $assinadoFile = __DIR__ . '/xml_assinado_debug.xml';
    file_put_contents($assinadoFile, $xmlAssinado);
    echo "XML assinado com sucesso! Arquivo: $assinadoFile\n";

} catch (Throwable $e) {
    echo "Erro ao assinar XML: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/debug_assinatura.txt', "[DEBUG] Erro ao assinar XML:\n" . $e->getMessage() . "\n", FILE_APPEND);
    exit(1);
}