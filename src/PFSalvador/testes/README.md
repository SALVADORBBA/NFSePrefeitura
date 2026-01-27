# Testes de Rotas NFS-e Salvador-BA

Este diretório contém scripts para testar a conectividade e funcionalidade dos webservices da NFS-e de Salvador-BA.

## 📋 Scripts Disponíveis

### 1. `testar_rotas_simples.php` - Teste Rápido
Teste básico e rápido para verificar se as URLs estão acessíveis.

```bash
php testar_rotas_simples.php
```

**O que testa:**
- ✅ Conectividade HTTP com os webservices
- ✅ Tempo de resposta básico
- ✅ Validação do formato WSDL

### 2. `TestarRotasSalvador.php` - Teste Completo
Teste detalhado com múltiplas opções de teste.

```bash
# Teste básico (sem certificado)
php TestarRotasSalvador.php

# Teste com certificado digital
php TestarRotasSalvador.php /caminho/certificado.pfx senha_do_certificado homologacao 

# Teste com certificado em produção (CUIDADO!)
php TestarRotasSalvador.php /caminho/certificado.pfx senha_do_certificado producao
```

**O que testa:**
- ✅ Conectividade SOAP completa
- ✅ Listagem de funções disponíveis
- ✅ Teste de latência (tempo de resposta)
- ✅ Comparação entre ambientes
- ✅ Conexão com certificado digital

### 3. `TestarFuncoesSOAP.php` - Análise Detalhada
Analisa e lista todas as funções SOAP disponíveis nos webservices.

```bash
# Menu interativo
php TestarFuncoesSOAP.php

# Executar todos os testes automaticamente
php TestarFuncoesSOAP.php 5
```

**O que testa:**
- ✅ Lista completa de funções SOAP
- ✅ Tipos de dados disponíveis
- ✅ Comparação entre homologação e produção
- ✅ Análise de latência detalhada

## 🔧 URLs Oficiais

### Ambiente de Homologação
```
https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl
```

### Ambiente de Produção
```
https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl
```

## 🎯 Interpretação dos Resultados

### Status de Conexão
- ✅ **SUCCESS**: Conexão estabelecida com sucesso
- ❌ **ERROR**: Falha na conexão
- ⚠️ **WARNING**: Conexão estabelecida mas com ressalvas

### Funções SOAP Comuns
As principais funções que devem estar disponíveis:

| Função | Descrição |
|--------|-----------|
| `RecepcionarLoteRps` | Enviar lote de RPS |
| `ConsultarSituacaoLoteRps` | Verificar situação do lote |
| `ConsultarLoteRps` | Consultar lote processado |
| `CancelarNfse` | Cancelar NFSe emitida |

### Tempos de Resposta Esperados
- **Ótimo**: < 500ms
- **Bom**: 500ms - 1s
- **Aceitável**: 1s - 3s
- **Lento**: > 3s

## 🚨 Erros Comuns e Soluções

### "Could not connect to host"
- Verifique sua conexão com a internet
- Confirme se a URL está correta
- Teste em outro horário (pode ser manutenção)

### "SSL/TLS error"
- Verifique se seu PHP tem suporte a SSL
- Atualize seus certificados CA
- Teste com `verify_peer => false` (apenas para testes)

### "WSDL not found"
- Confirme se a URL está acessível no navegador
- Verifique se é o endpoint correto
- Teste com `testar_rotas_simples.php` primeiro

### "Certificate error"
- Verifique se o certificado é válido
- Confirme a senha do certificado
- Teste o certificado com openssl:
  ```bash
  openssl pkcs12 -in certificado.pfx -noout
  ```

## 💡 Dicas Importantes

1. **Sempre teste em homologação primeiro!**
2. **Certificados**: Use certificados A1 válidos para testes reais
3. **Horário**: Evite testes em horários de pico (9h-11h, 14h-16h)
4. **Frequência**: Não teste excessivamente para não sobrecarregar os servidores
5. **Monitoramento**: Salve os logs dos testes para análise futura

## 🔍 Testes Adicionais

### Testar com cURL
```bash
# Testar homologação
curl -I https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl

# Testar produção
curl -I https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl
```

### Testar com telnet
```bash
# Testar porta 443 (HTTPS)
telnet notahml.salvador.ba.gov.br 443
telnet nfse.salvador.ba.gov.br 443
```

## 📞 Suporte

Se os testes falharem consistentemente:
1. Verifique o [site oficial da prefeitura](https://www.salvador.ba.gov.br)
2. Consulte o [portal da NFS-e de Salvador](https://nfse.salvador.ba.gov.br)
3. Entre em contato com o suporte técnico da prefeitura
4. Verifique se há manutenções programadas

## 📝 Exemplos de Uso

### Exemplo de Teste Básico
```bash
$ php testar_rotas_simples.php
🧪 TESTE RÁPIDO DAS ROTAS NFS-e SALVADOR-BA
==================================================

📡 Testando: HOMOLOGAÇÃO
🔗 URL: https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl
✅ CONEXÃO OK! (245ms)
✅ WSDL VÁLIDO DETECTADO!
--------------------------------------------------

📡 Testando: PRODUÇÃO
🔗 URL: https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl
✅ CONEXÃO OK! (189ms)
✅ WSDL VÁLIDO DETECTADO!
--------------------------------------------------

✅ Teste rápido finalizado!
```

### Exemplo de Teste com Certificado
```bash
$ php TestarRotasSalvador.php /home/user/certificado.pfx minhasenha homologacao
🔐 Testando conexão com certificado digital...
========================================

✅ Conexão com certificado estabelecida! (Erro esperado: dados de teste)
⚠️  Erro esperado (dados de teste): Protocolo TESTE123 não encontrado
```

---

**Lembrete**: Estes testes são apenas para verificação de conectividade. Para operações reais, sempre use dados válidos e certificados digitais oficiais!