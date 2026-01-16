<?php

use NFSePrefeitura\NFSe\PortoSeguro;
class TesteController extends TPage
{
    public function __construct($param)
    {
        parent::__construct();
    }

    public function onShow($param = null)
    {
        // Caminho e senha do certificado digital
        $certPath = 'app/certificado/1/6968c3b3791e6_695fa57fadf87_quimica.pfx';
        $certPass = 'baby7902';

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
        self::salvar('xmlLote.xml', $xmlLote);

        // 2. Salvar XML em arquivo temporário para assinatura externa
        $tempXmlPath = __DIR__ . '/temp_lote.xml';
        file_put_contents($tempXmlPath, $xmlLote);

        // 3. Chamar assinador externo (ajuste o caminho conforme seu ambiente)
        $assinadorPath = 'C:\AssinadorXML&ComunicacaoWS\AssinadorDeXML.exe'; // ajuste se necessário
        $outputXmlPath = __DIR__ . '/temp_lote_assinado.xml';
        $cmd = '"' . $assinadorPath . '" "' . $tempXmlPath . '" "' . $certPath . '" "' . $certPass . '" "' . $outputXmlPath . '"';
        exec($cmd, $output, $retorno);

        // 4. Ler XML assinado e salvar
        if ($retorno === 0 && file_exists($outputXmlPath)) {
            $xmlLoteAssinado = file_get_contents($outputXmlPath);
            self::salvar('xmlLoteAssinado.xml', $xmlLoteAssinado);
            header('Content-Type: text/xml; charset=utf-8');
            echo $xmlLoteAssinado;
        } else {
            echo 'Erro ao assinar o XML!';
        }
    }
    private static function salvar(string $nome, string $conteudo): void
    {
        $dir = 'app/xml_nfse/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . date('Ymd_His_') . $nome, $conteudo);
    }
}