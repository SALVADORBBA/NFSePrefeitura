<?php

use NFSePrefeitura\NFSe\PFNatal\Natal;
 

class GerarAssinarNatalXML
{
    private string $certPath;
    private string $certPassword;

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    public function create(): string
    {
        // ===============================
        // PASSO 1 - Dados
        // ===============================
        $jsonData = $this->getJsonData();
        echo "<pre>PASSO 1 - dados carregados</pre>";

        // ===============================
        // PASSO 2 - Gerar XML
        // ===============================
        $Natal = new Natal(
            $this->certPath,
            $this->certPassword
        );

        $xml = $Natal->gerarXmlLoteRps($jsonData);

        $arquivoXml = $this->salvar(
            '01_inicial.xml',
            'app/xml_nfse/Natal',
            $xml
        );

        echo "<pre>PASSO 2 - XML gerado\nArquivo: {$arquivoXml}</pre>";

        // ===============================
        // PASSO 3 - Assinar XML
        // ===============================
        $Assinatura = new \NFSePrefeitura\NFSe\PFNatal\AssinaturaNatal();
        $xmlAssinado = $Assinatura->assinarXml($xml, $this->certPath, $this->certPassword);

        $arquivoXmlAssinado = $this->salvar(
            '02_assinado.xml',
            'app/xml_nfse/Natal',
            $xmlAssinado
        );

        echo "<pre>PASSO 3 - XML assinado\nArquivo: {$arquivoXmlAssinado}</pre>";

        return $xmlAssinado;
    }

    // =====================================================
    // Dados simulados (depois pode vir do banco)
    // =====================================================
    private function getJsonData(): array
    {
        return [
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
                    'valorServicos' => 3.50,
                    'valorIss' => 0,
                    'aliquota' => 2,
                    'issRetido' => 2,
                    'itemListaServico' => '1401',
                    'discriminacao' => 'SERVIÇOS PRESTADOS NA O.S.',
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
    }

    // =====================================================
    // Salvar arquivos
    // =====================================================
    private function salvar(string $nome, string $dir, string $conteudo): string
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $arquivo = rtrim($dir, '/\\') . '/' . date('Ymd_His_') . $nome;
        file_put_contents($arquivo, $conteudo);

        return $arquivo;
    }
}