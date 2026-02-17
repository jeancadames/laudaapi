import { SignedXml } from 'xml-crypto';
import { DOMParser, XMLSerializer } from '@xmldom/xmldom';
import keyInfoContent from './custom/KeyInfoProvider.js';
import Digest from './custom/Digest.js';

export default class Signature {
  _privateKey = '';
  _certificatePEM = '';

  constructor(privateKeyPem, certificatePEM) {
    this._privateKey = privateKeyPem;
    this._certificatePEM = certificatePEM;
  }

  cleanNodes = (node) => {
    for (let n = 0; n < node.childNodes.length; n++) {
      const child = node.childNodes[n];
      if (child.nodeType === 8 || (child.nodeType === 3 && !/\S/.test(child.nodeValue))) {
        node.removeChild(child);
        n--;
      } else if (child.nodeType === 1) {
        this.cleanNodes(child);
      }
    }
  };

  signXml = (xml, rootElName) => {
    const sig = new SignedXml({
      privateKey: this._privateKey,
      publicCert: this._certificatePEM,
      signatureAlgorithm: 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
      canonicalizationAlgorithm: 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
    });

    // ⬇️ Registrar el digest custom en la INSTANCIA (v6)
    sig.HashAlgorithms['http://myDigestAlgorithm'] = Digest;

    // KeyInfo sin prefijo ds:
    sig.getKeyInfoContent = () => keyInfoContent(this._certificatePEM);

    const xpath = `/${rootElName}`;  // ej: /SemillaModel

    sig.addReference({
      xpath,
      transforms: ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
      digestAlgorithm: 'http://myDigestAlgorithm',   // usa tu Digest custom
      isEmptyUri: true,                               // Reference URI=""
    });

    const doc = new DOMParser().parseFromString(xml, 'text/xml');
    this.cleanNodes(doc);
    const unsigned = new XMLSerializer().serializeToString(doc);

    sig.computeSignature(unsigned, {
      prefix: '',                                     // sin "ds:"
      location: { reference: xpath, action: 'append' }
    });

    return sig.getSignedXml().replace(/>\s+</g, '><').trim();
  };
}
