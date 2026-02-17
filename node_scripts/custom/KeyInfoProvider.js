import forge from 'node-forge';

export default function keyInfoContent(certificatePEM) {
  if (Buffer.isBuffer(certificatePEM)) certificatePEM = certificatePEM.toString('ascii');
  if (!certificatePEM || typeof certificatePEM !== 'string') {
    throw new Error('certificatePEM must be a valid certificate in PEM format');
  }
  const body = forge.pem.decode(certificatePEM)[0].body;
  const b64  = forge.util.encode64(body);
  // sin prefijo ds:, namespace por defecto lo aporta xml-crypto
  return `<X509Data><X509Certificate>${b64}</X509Certificate></X509Data>`;
}
