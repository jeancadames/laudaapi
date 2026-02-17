#!/usr/bin/env node
import fs from 'fs'
import forge from 'node-forge'
import { DOMParser } from '@xmldom/xmldom'
import Signature from './Signature.js'

function loadP12(p12Path, password) {
  const der = fs.readFileSync(p12Path, { encoding: 'binary' })
  const asn1 = forge.asn1.fromDer(der)
  const p12 = forge.pkcs12.pkcs12FromAsn1(asn1, password)

  const keyBag =
    p12.getBags({ bagType: forge.pki.oids.pkcs8ShroudedKeyBag })[forge.pki.oids.pkcs8ShroudedKeyBag]?.[0] ||
    p12.getBags({ bagType: forge.pki.oids.keyBag })[forge.pki.oids.keyBag]?.[0]
  if (!keyBag?.key) throw new Error('No se pudo extraer la private key del P12.')
  const privateKeyPem = forge.pki.privateKeyToPem(keyBag.key)

  const certBag = p12.getBags({ bagType: forge.pki.oids.certBag })[forge.pki.oids.certBag]?.[0]
  if (!certBag?.cert) throw new Error('No se pudo extraer el certificado del P12.')
  const certPem = forge.pki.certificateToPem(certBag.cert)

  return { privateKeyPem, certPem }
}

function getRootTagName(xml) {
  const doc = new DOMParser().parseFromString(xml, 'text/xml')
  const root = doc?.documentElement
  if (!root) throw new Error('XML inválido: sin elemento raíz.')
  return root.localName || root.nodeName
}

;(async () => {
  try {
    // Uso: node signSeedOnly.js <passFile> <p12Path> <xmlPath> <outSignedXmlPath>
    const [passFile, p12Path, xmlPath, outSignedXmlPath] = process.argv.slice(2)
    if (!passFile || !p12Path || !xmlPath || !outSignedXmlPath) {
      console.error('Uso: node signSeedOnly.js <passFile> <p12Path> <xmlPath> <outSignedXmlPath>')
      process.exit(2)
    }

    const password = fs.readFileSync(passFile, 'utf8').trim()
    const { privateKeyPem, certPem } = loadP12(p12Path, password)
    const xml = fs.readFileSync(xmlPath, 'utf8')

    const rootElName = getRootTagName(xml) // ej: SemillaModel

    const signer = new Signature(privateKeyPem, certPem)
    const signedXml = signer.signXml(xml, rootElName)

    fs.writeFileSync(outSignedXmlPath, signedXml, 'utf8')
    process.exit(0)
  } catch (err) {
    console.error(err?.message || err)
    process.exit(1)
  }
})()