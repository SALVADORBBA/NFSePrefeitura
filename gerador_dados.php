<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use NFSePrefeitura\NFSe\PFChapeco\Chapeco;
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
 * 📄 DADOS DO LOTE RPS
 * =====================================================
 */
$dadosLote = [
    'lote_id'            => '1',
    'numeroLote'         => 1,
    'cnpjPrestador'      => '00753494000185',
    'inscricaoMunicipal' => '185663',
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
            'tomador' => [
                'cnpj' => '57219214553',
                'razaoSocial' => 'Rubens dos Santos',
                'endereco' => 'Rua Porto Seguro',
                'numero' => '12',
                'complemento' => 'D',
                'bairro' => 'Centro',
                'codigoMunicipio' => '4204202',
                'uf' => 'SC',
                'cep' => '89802520',
                'telefone' => '4933223046',
                'email' => 'teste@exemplo.com',
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
    $nfse = new Chapeco($certPath, $certPassword);
    $xmlGerado = $nfse->gerarXmlLoteRps($dadosLote);

    // Exibe o XML gerado antes da assinatura para inspeção
    echo "\n===== XML GERADO ANTES DA ASSINATURA =====\n";
    echo $xmlGerado;
    echo "\n==========================================\n";


    } catch (Exception $e) {
    echo "Erro NFSe: " . $e->getMessage();
}
try {
    // Assinatura do XML usando AssinaturaChapeco
    $assinador = new AssinaturaChapeco($certPath, $certPassword);
    $xmlAssinado = $assinador->assinarLoteRps($xmlGerado);
 
    salvarXml('xml_assinado.xml', $xmlAssinado);
    echo "XML gerado e assinado com sucesso!";

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
}