using System;
using System.Collections.Generic;
using System.Security.Cryptography.Xml;
using System.Security.Cryptography.X509Certificates;
using System.Xml;

namespace NotasFiscais.Abrasf
{
	public class AssinadorDeXML
	{
		private X509Certificate2 _certificado { get; set; }
		
		// Assina o XML passado no nó informado.
		public string AssinarXML(string xml, string node, X509Certificate2 certificado)
		{
			if (certificado == null)
				throw new ArgumentException("Certificado não configurado");

			_certificado = certificado;

			VerificarParametros(xml, node);

			XmlDocument xmlDoc = new XmlDocument();
			xmlDoc.PreserveWhitespace = true;
			xmlDoc.LoadXml(xml);

			var nodes = SelecionarNos(xmlDoc.ChildNodes, node);

			if (nodes.Count == 0)
				return xml;

			foreach (XmlElement xnode in nodes)
			{
				var assinado = AssinarElemento(xnode);
				var importado = xmlDoc.ImportNode(assinado, true);
				xnode.AppendChild(importado);
			}

			return xmlDoc.OuterXml;
		}

		private void VerificarParametros(string xml, string node)
		{
			if (xml == null)
				throw new ArgumentNullException("xml", "Não é possível assinar digitalmente um xml nulo");

			if (node == null)
				throw new ArgumentNullException("node", "Não é possível assinar digitalmente um xml sem um elemento nó raiz");
		}

		private ICollection<XmlNode> SelecionarNos(XmlNodeList nodes, string node)
		{
			var list = new List<XmlNode>();

			XmlNode current;
			for (int i = 0; i < nodes.Count; ++i)
			{
				current = nodes[i];

				if (current.Name == node)
				{
					list.Add(current);
				}
				else if (current.HasChildNodes)
				{
					list.AddRange(SelecionarNos(current.ChildNodes, node));
				}
			}

			return list;
		}

		private XmlElement AssinarElemento(XmlElement xmlDoc)
		{
			SignedXml signedXml = new SignedXml(xmlDoc);
			signedXml.SigningKey = _certificado.PrivateKey;

			Reference reference = new Reference();
			reference.AddTransform(new XmlDsigEnvelopedSignatureTransform());
			reference.AddTransform(new XmlDsigC14NTransform());
			reference.Uri = ObterId(xmlDoc);

			signedXml.AddReference(reference);

			KeyInfo keyInfo = new KeyInfo();
			keyInfo.AddClause(new KeyInfoX509Data(_certificado));

			signedXml.KeyInfo = keyInfo;

			signedXml.ComputeSignature();

			XmlElement xmlDigitalSignature = signedXml.GetXml();

			return xmlDigitalSignature;
		}

		private string ObterId(XmlElement doc)
		{
			XmlAttribute idElemento = (XmlAttribute) doc.SelectSingleNode(".//@Id | .//@id");

			if (idElemento == null)
				throw new System.Exception("Elemento ID nao encontrado no xml");

			return "#" + idElemento.Value;
		}

	}
}
