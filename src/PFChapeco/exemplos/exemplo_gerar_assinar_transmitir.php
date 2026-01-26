<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use NFSePrefeitura\NFSe\PFChapeco\Chapeco;
use NFSePrefeitura\NFSe\PFChapeco\AssinaturaChapeco;
    use NFSePrefeitura\NFSe\PFChapeco\TransmitirChapeco;
/**
 * =====================================================
 * 🔐 CONFIGURAÇÃO DO CERTIFICADO
 * =====================================================
 */
$certPath     = __DIR__ . '/certificado/certificado_od.pfx';
$certPassword = '15021502';

/**
 * =====================================================
 * 📄 DADOS DO LOTE RPS
 * =====================================================
 */
$dadosLote = [
    'lote_id'            => '1',
    'numeroLote'         => 1,
    'cnpjPrestador'      => '12345678901122',
    'inscricaoMunicipal' => '1212222',
    'quantidadeRps'      => 1,
    'rps' => [
        [
            'numero'      => 1,
            'serie'       => '1',
            'tipo'        => 1,
            'dataEmissao' => '2026-01-18',
              'status' => 1,
            'competencia'               => '2026-01-01',
            'valorServicos'            => 5.00,
            'valorIss'                 => 0,
            'aliquota'                 => 0,
            'issRetido'                => 2,
             'codigoPais'                => 1058,
                      'exigibilidadeISS'                =>1,

            'itemListaServico'         => '1401',
            'codigoCnae'               => '6201501',
            'discriminacao'            => 'Serviço de exemplo',
            'cNBS'                     => '115022000',
            'codigoMunicipio'          => '4204202',
            'municipioIncidencia'      => '4204202',
            'localidadeIncidencia'     => 'Chapeco',
            'incentivoFiscal'          => 1,
            'optanteSimplesNacional'   => 1,
            'regimeEspecialTributacao' => 2,
            'informacoesComplementares' => 'nada',
            'valorDeducoes'            => 0,
            'valorPis'                 => 0,
            'valorCofins'              => 0,
            'valorInss'                => 0,
            'valorIr'                  => 0,
            'valorCsll'                => 0,
            'outrasRetencoes'          => 0,
            'valTotTributos'           => 0,
            'descontoIncondicionado'   =>'0.00',
            'responsavelRetencao'   =>  '1',
          
            'tomador' => [
                'cnpj' => '123456789011',
                'razaoSocial' => 'Rubens dos Santos',
                'endereco' => 'Rua Porto Seguro',
                'numero' => '12',
                'complemento' => 'D',
                'bairro' => 'Centro',
                'codigoMunicipio' => '4204202',
                'uf' => 'SC',
                'cep' => '89802520',
                'telefone' => '71996758059',
                'email' => 'salvadorbb@gmail.com.com',
            ],
        ],
    ],
];

try {
    /**
     * =====================================================
     * ⚙️ GERA XML
     * =====================================================
     */
    // Gera XML
    echo "\n[PASSO] Iniciando geração do XML...\n";
    $nfse = new Chapeco($certPath, $certPassword);
    $xmlGerado = $nfse->gerarXmlLoteRps($dadosLote);
    if ($xmlGerado instanceof \DOMDocument) {
        $xmlGerado = $xmlGerado->saveXML();
    }
    salvarXml('xml_inicial.xml', $xmlGerado);
    echo "\n===== XML GERADO ANTES DA ASSINATURA =====\n";
    echo $xmlGerado;
    echo "\n==========================================\n";
    echo "\n[PASSO] XML gerado com sucesso!\n";


    } catch (Exception $e) {
    echo "Erro NFSe: " . $e->getMessage();
}
try {
    // Assinatura do XML usando AssinaturaChapeco
    echo "\n[PASSO] Iniciando assinatura do XML...\n";
    $assinador = new \NFSePrefeitura\NFSe\PFChapeco\AssinaturaChapeco(
        $certPath, $certPassword
    );
    $xmlAssinado = $assinador->assinarRps($xmlGerado);
    if ($xmlAssinado instanceof \DOMDocument) {
        $xmlAssinado = $xmlAssinado->saveXML();
    }
    salvarXml('xml_assinado.xml', $xmlAssinado);
    echo "\n===== XML ASSINADO =====\n";
    echo $xmlAssinado;
    echo "\n========================\n";
    echo "\n[PASSO] XML assinado com sucesso!\n";
    // Após assinar, transmitir para Chapecó

    if (!empty($xmlAssinado)) { 
        $resposta = \NFSePrefeitura\NFSe\PFChapeco\TransmitirChapeco::transmitirLote($xmlAssinado);
    
        // Se a resposta for um objeto, acessa o campo outputXML
        if (is_object($resposta) && isset($resposta->RecepcionarLoteRpsResponse->outputXML)) {
            $xmlResposta = $resposta->RecepcionarLoteRpsResponse->outputXML;
        } else {
            // Se já for string, usa direto
            $xmlResposta = (string)$resposta;
        }
    
        salvarXml('xml_resposta.xml', $xmlResposta);
        echo "Transmissão realizada! Resposta salva em xml_resposta.xml";
    }

} catch (Throwable $e) {
    echo "Erro NFSe: " . $e->getMessage();

 
}

/**
 * =====================================================
 * 💾 FUNÇÃO PARA SALVAR XML
 * =====================================================
 */
function salvarXml(string $nomeArquivo, string $conteudo): void
{
    $dir = __DIR__ . '/app/xml_nfse/';

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $nomeFinal = date('Ymd_His_') . $nomeArquivo;
    file_put_contents($dir . $nomeFinal, $conteudo);
    // Salva o XML gerado para debug
     
    echo "XML do lote gerado e salvo em xml_gerado_debug.xml\n";
}