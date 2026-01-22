<?php

use NFSePrefeitura\NFSe\PFNatal\Natal;
use NFSePrefeitura\NFSe\PFNatal\AssinaturaNatal;
use NFSePrefeitura\NFSe\PFNatal\EnvioNatal;

class GerarAssinarNatalXML
{
    private string $certPath;
    private string $certPassword;

    /** Base de pastas (pode alterar para app_path(...) se estiver no Laravel) */
    private string $baseDir = 'app/xml_nfse/Natal';

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    /**
     * Executa o fluxo completo:
     * 1) carrega dados
     * 2) gera XML
     * 3) assina
     * 4) envia
     *
     * Retorna o SOAP Response XML (útil para debug/auditoria)
     */
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

        } catch (\Throwable $e) {
            $this->log("ERRO: " . $e->getMessage());
            throw $e;
        }
    }

    // =====================================================
    // PASSO 2 - Geração XML
    // =====================================================

    private function gerarXml(array $dados): string
    {
        $natal = new Natal($this->certPath, $this->certPassword);
        $xml   = (string) $natal->gerarXmlLoteRps($dados);

        $arquivo = $this->salvarArquivo('01_inicial.xml', $this->dir('Inicial'), $xml);
        $this->log("PASSO 2 - XML gerado\nArquivo: {$arquivo}");

        return $xml;
    }

    // =====================================================
    // PASSO 3 - Assinatura XML
    // =====================================================

    private function assinarXml(string $xml): string
    {
        $assinatura  = new AssinaturaNatal($this->certPath, $this->certPassword);
        $xmlAssinado = (string) $assinatura->assinarLoteRps($xml);

        $arquivo = $this->salvarArquivo('02_assinado.xml', $this->dir('Assinados'), $xmlAssinado);
        $this->log("PASSO 3 - XML assinado\nArquivo: {$arquivo}");

        return $xmlAssinado;
    }

    // =====================================================
    // PASSO 4 - Envio SOAP
    // =====================================================

    /**
     * Retorna o SOAP RESPONSE XML como string.
     * Também salva request/response + objeto retornado em JSON.
     */
    private function enviarParaWebservice(string $xmlAssinado): string
    {
        $envio = new EnvioNatal($this->certPath, $this->certPassword);

        // retorno do SoapClient (geralmente objeto/struct)
        $retObj = $envio->enviarLoteRps($xmlAssinado);

        // salva objeto em JSON (para análise)
        $arquivoObj = $this->salvarArquivo('03_response_obj.json', $this->dir('Respostas'), $retObj);
        $this->log("PASSO 4 - Retorno objeto salvo\nArquivo: {$arquivoObj}");

        // tenta pegar o SOAP bruto se EnvioNatal tiver getters
        $soapRequest  = null;
        $soapResponse = null;

        if (method_exists($envio, 'getLastRequest')) {
            $soapRequest = $envio->getLastRequest();
        }
        if (method_exists($envio, 'getLastResponse')) {
            $soapResponse = $envio->getLastResponse();
        }

        if ($soapRequest) {
            $arquivoReq = $this->salvarArquivo('03_request_soap.xml', $this->dir('Respostas'), (string)$soapRequest);
            $this->log("PASSO 5 - SOAP REQUEST salvo\nArquivo: {$arquivoReq}");
        }

        if ($soapResponse) {
            $arquivoRes = $this->salvarArquivo('03_response_soap.xml', $this->dir('Respostas'), (string)$soapResponse);
            $this->log("PASSO 6 - SOAP RESPONSE salvo\nArquivo: {$arquivoRes}");

            // ✅ retorna o SOAP Response XML (string) — mais útil e coerente com assinatura do método
            return (string)$soapResponse;
        }

        // fallback: se não tiver lastResponse, devolve JSON do objeto
        return $this->normalizeConteudo($retObj);
    }

    // =====================================================
    // Helpers
    // =====================================================

    private function dir(string $sub): string
    {
        return rtrim($this->baseDir, '/\\') . '/' . trim($sub, '/\\');
    }

    private function log(string $mensagem): void
    {
        echo "<pre>{$mensagem}</pre>";
    }

    /**
     * Salva qualquer coisa (string|array|object) como arquivo.
     * Retorna caminho completo do arquivo salvo.
     */
    private function salvarArquivo(string $nome, string $dir, $conteudo): string
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $arquivo = rtrim($dir, '/\\') . '/' . date('Ymd_His_') . $nome;

        $data = $this->normalizeConteudo($conteudo);
        file_put_contents($arquivo, $data);

        return $arquivo;
    }

    /**
     * Converte conteúdo em string segura para salvar.
     */
    private function normalizeConteudo($conteudo): string
    {
        if ($conteudo === null) {
            return '';
        }

        if (is_string($conteudo)) {
            return $conteudo;
        }

        if (is_scalar($conteudo)) {
            return (string) $conteudo;
        }

        // array / object
        return json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
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
                    // IBSCBS (reforma tributária)
                    'IBSCBS' => [
                        'cLocalidadeIncid' => '2925303',
                        'xLocalidadeIncid' => 'Salvador',
                        'valores' => [
                            'vBC' => 3.50,
                            'uf' => [
                                'pIBSUF' => 0.01,
                                'pAliqEfetUF' => 0.02
                            ],
                            'mun' => [
                                'pIBSMun' => 0.03,
                                'pAliqEfetMun' => 0.04
                            ],
                            'fed' => [
                                'pCBS' => 0.05,
                                'pAliqEfetCBS' => 0.06
                            ]
                        ],
                        'totCIBS' => [
                            'vTotNF' => 3.50,
                            'gIBS' => [
                                'vIBSTot' => 1.00,
                                'gIBSUFTot' => [
                                    'vIBSUF' => 0.50
                                ],
                                'gIBSMunTot' => [
                                    'vIBSMun' => 0.50
                                ]
                            ],
                            'gCBS' => [
                                'vCBS' => 0.20
                            ]
                        ]
                    ],
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
}