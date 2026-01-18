
// Exemplo de metodo de conexao
// O proxy do servico NfseWSService foi gerado a partir do utilitario WSDL.exe
// https://msdn.microsoft.com/en-us/library/7h3ystb6(v=vs.100).aspx
// https://technet.microsoft.com/pt-br/library/7h3ystb6(v=vs.100).aspx
public void ConexaoWebiss()
{
	EnviarLoteRpsEnvio envio = ObterDadosEnvio();

	string xmlCabecalho = GerarXMLCabecalho();
	string xmlDados = GerarXML(envio);
	xmlDados = AssinarXML(xmlDados, "Rps");
	xmlDados = AssinarXML(xmlDados, "EnviarLoteRpsEnvio");
		
	NfseWSService servico = new NfseWSService();
	servico.Url = UrlProducaoWebiss;
	servico.Timeout = Timeout;
	
	string xmlRetorno = servico.RecepcionarLoteRps(xmlCabecalho, xmlDados);
}

private string AssinarXML(string xml, string node)
{
	AssinadorDeXML assinador = new AssinadorDeXML();
	assinador.AssinarXML(xml, node, ObterCertificado());
}