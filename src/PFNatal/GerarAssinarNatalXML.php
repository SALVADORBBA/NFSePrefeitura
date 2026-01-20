<?php

use NFSePrefeitura\NFSe\PFNatal\Natal;
 use NFSePrefeitura\NFSe\PFNatal\AssinaturaNatal;
use NFSePrefeitura\NFSe\PFNatal\EnvioNatal;

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
        try {
            // PASSO 1 - Obter dados
            $jsonData = $this->getJsonData();
            $this->log("PASSO 1 - Dados carregados");

            // PASSO 2 - Gerar XML
            $xml = $this->gerarXml($jsonData);
            
            // PASSO 3 - Assinar XML
            $xmlAssinado = $this->assinarXml($xml);
            
            // PASSO 4 - Enviar para webservice
            return $this->enviarParaWebservice($xmlAssinado);
            
        } catch (Exception $e) {
            $this->log("ERRO: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function gerarXml(array $dados): string
    {
        $Natal = new Natal($this->certPath, $this->certPassword);
        $xml = $Natal->gerarXmlLoteRps($dados);
        
        $arquivo = $this->salvar('01_inicial.xml', 'app/xml_nfse/Natal/Inicial', $xml);
        $this->log("PASSO 2 - XML gerado\nArquivo: {$arquivo}");
        
        return $xml;
    }
    
    private function assinarXml(string $xml): string
    {
        $Assinatura = new AssinaturaNatal($this->certPath, $this->certPassword);
        $xmlAssinado = $Assinatura->assinarLoteRps($xml);
        
        $arquivo = $this->salvar('02_assinado.xml', 'app/xml_nfse/Natal/Assinados', $xmlAssinado);
        $this->log("PASSO 3 - XML assinado\nArquivo: {$arquivo}");
        
        return $xmlAssinado;
    }
    
    private function enviarParaWebservice(string $xmlAssinado): string
    {
        $Natal = new EnvioNatal($this->certPath, $this->certPassword);
        $response = $Natal->enviarLoteRps($xmlAssinado);
        
        $arquivo = $this->salvar('03_resposta.xml', 'app/xml_nfse/Natal/Respostas', $response);
        $this->log("PASSO 4 - Enviado para webservice\nArquivo: {$arquivo}");
        
        return $response;
    }
    
    private function log(string $mensagem): void
    {
        echo "<pre>{$mensagem}</pre>";
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
                    'naturezaOperacao' => '1',
                    'incentivadorCultural' => '2',
                    'status' => '1',
                    'valorServicos' => 3.50,
                    'valorPis' => 0,
                    'valorCofins' => 0,
                    'valorCsll' => 0,
                    'valorIss' => 0,
                    'valorIssRetido' => 0,
                    'baseCalculo' => 3.50,
                    'aliquota' => 2,
                    'issRetido' => 2,
                    'descontoIncondicionado' => 0,
                    'descontoCondicionado' => 0,
                    'itemListaServico' => '1401',
                    'discriminacao' => 'SERVIÇOS PRESTADOS NA O.S.',
                    'codigoMunicipio' => '2925303',
                    'codigoTributacaoMunicipio' => '1401',
                    'exigibilidadeISS' => '1',
                    'regimeEspecialTributacao' => 6,
                    'optanteSimplesNacional' => 1,
                    'incentivoFiscal' => 2,
                    'codigoCnae' => '4520007',
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