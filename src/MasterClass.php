<?php

namespace NFSePrefeitura\NFSe;

class MasterClass
{
    public function gerarNumeroRps(
        string $serie,
        int $numero,
        string $cnpj
    ) {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $cnpjPrefixo = substr($cnpj, 0, 3);
        $timestamp = date('ymdHis');
        $aleatorio = random_int(10, 99);
        $rps = $serie
            . str_pad($numero, 3, '0', STR_PAD_LEFT)
            . $cnpjPrefixo
            . $timestamp
            . $aleatorio;
        return substr($rps, 0, 20);
    }

    public function removerAcentos($texto)
    {
        $texto = preg_replace('/[áàâãªä]/ui', 'a', $texto);
        $texto = preg_replace('/[éèêë]/ui', 'e', $texto);
        $texto = preg_replace('/[íìîï]/ui', 'i', $texto);
        $texto = preg_replace('/[óòôõö]/ui', 'o', $texto);
        $texto = preg_replace('/[úùûü]/ui', 'u', $texto);
        $texto = preg_replace('/[ç]/ui', 'c', $texto);
        $texto = preg_replace('/[^!-ÿ]/u', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        $texto = trim($texto);
        if (strlen($texto) < 1) {
            $texto = 'N/A';
        }
        return $texto;
    }

    public function MoedaNF($valor)
    {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        $valor = floatval($valor);
        $valor_truncado = floor($valor * 100) / 100;
        return number_format($valor_truncado, 2, ',', '.');
    }

    public function calcDescPercentual(float $total, float $desconto): float
    {
        if ($total <= 0 || $desconto < 0 || $desconto > $total) {
            return 0;
        }
        return round(($desconto / $total) * 100, 2);
    }

    public function descPercentual($valor1, $valor2)
    {
        $valor1 = str_replace(',', '.', str_replace('.', '', $valor1));
        $valor2 = str_replace(',', '.', str_replace('.', '', $valor2));
        $valor1 = (float) $valor1;
        $valor2 = (float) $valor2;
        if ($valor1 <= 0) {
            return 'Erro: Valor base inválido.';
        }
        $lucro = $valor2 - $valor1;
        $percentual = ($lucro / $valor1) * 100;
        return number_format($percentual, 2, ',', '.') . '%';
    }

    public function diferencaPercentual($total, $valor)
    {
        $total = str_replace(',', '.', str_replace('.', '', $total));
        $valor = str_replace(',', '.', str_replace('.', '', $valor));
        $total = (float) $total;
        $valor = (float) $valor;
        if ($total <= 0 || $valor < 0) {
            return 0;
        }
        $percentual = ($valor / $total) * 100;
        return round($percentual, 2);
    }

    public function TrataDoc(?string $valor): string
    {
        if (is_null($valor)) {
            return '';
        }
        return str_replace(['+', '.', '-', '/', '(', ')', ' '], '', $valor);
    }

    /**
     * Valida campos obrigatórios do lote (deve estar em MasterClass)
     */
    public function validarLote(array $dados): void
    {
        if (!isset($dados['numeroLote']) || empty($dados['numeroLote'])) {
            throw new \InvalidArgumentException('Número do lote não informado.');
        }
        if (!isset($dados['cnpjPrestador']) || !$this->isCnpjValido($dados['cnpjPrestador'])) {
            throw new \InvalidArgumentException('CNPJ do prestador inválido ou não informado.');
        }
        if (!isset($dados['inscricaoMunicipal']) || !$this->isInscricaoMunicipalValida($dados['inscricaoMunicipal'])) {
            throw new \InvalidArgumentException('Inscrição municipal do prestador inválida ou não informada.');
        }
        if (!isset($dados['quantidadeRps']) || (int)$dados['quantidadeRps'] < 1) {
            throw new \InvalidArgumentException('Quantidade de RPS inválida ou não informada.');
        }
        if (!isset($dados['rps']) || !is_array($dados['rps']) || count($dados['rps']) < 1) {
            throw new \InvalidArgumentException('Nenhum RPS informado no lote.');
        }
    }

    /**
     * Valida campos obrigatórios do RPS (deve estar em MasterClass)
     */
    public function validarRps(array $rps): void
    {
        if (!isset($rps['inf_id']) || empty($rps['inf_id'])) {
            throw new \InvalidArgumentException("inf_id do RPS não informado.");
        }
        if (!isset($rps['infRps']) || !is_array($rps['infRps'])) {
            throw new \InvalidArgumentException("infRps do RPS não informado.");
        }
        $inf = $rps['infRps'];
        if (!isset($inf['numero']) || !is_numeric($inf['numero'])) {
            throw new \InvalidArgumentException("Número do RPS inválido ou não informado.");
        }
        if (!isset($inf['serie']) || empty($inf['serie'])) {
            throw new \InvalidArgumentException("Série do RPS não informada.");
        }
        if (!isset($inf['tipo']) || !in_array($inf['tipo'], [1,2,3])) {
            throw new \InvalidArgumentException("Tipo do RPS inválido ou não informado.");
        }
        if (!isset($inf['dataEmissao']) || !$this->isDataValida($inf['dataEmissao'])) {
            throw new \InvalidArgumentException("Data de emissão do RPS inválida ou não informada.");
        }
        if (!isset($rps['valorServicos']) || !is_numeric($rps['valorServicos'])) {
            throw new \InvalidArgumentException("Valor dos serviços inválido ou não informado.");
        }
        if (!isset($rps['tomador']) || !is_array($rps['tomador'])) {
            throw new \InvalidArgumentException("Tomador do RPS não informado.");
        }
        $tom = $rps['tomador'];
        if (!isset($tom['cpfCnpj']) || !$this->isCpfCnpjValido($tom['cpfCnpj'])) {
            throw new \InvalidArgumentException("CPF/CNPJ do tomador inválido ou não informado.");
        }
        if (!isset($tom['razaoSocial']) || empty($tom['razaoSocial'])) {
            throw new \InvalidArgumentException("Razão social do tomador não informada.");
        }
        if (!isset($tom['endereco']) || !is_array($tom['endereco'])) {
            throw new \InvalidArgumentException("Endereço do tomador não informado.");
        }
        $end = $tom['endereco'];
        foreach ([
            'logradouro', 'numero', 'bairro', 'codigoMunicipio', 'uf', 'cep'
        ] as $k) {
            if (!isset($end[$k]) || empty($end[$k])) {
                throw new \InvalidArgumentException("Endereço do tomador: campo obrigatório '{$k}' não informado.");
            }
        }
    }
}