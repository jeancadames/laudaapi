import crypto from 'crypto';
import { DOMParser } from '@xmldom/xmldom';

export default class Digest {
  sortElements = (elements) => {
    const items = Array.from(elements);
    items.sort((a, b) => (a < b ? -1 : a > b ? 1 : 0));
    return items;
  };

  getHash = (xml) => {
    const doc = new DOMParser().parseFromString(xml, 'text/xml');
    const root = doc?.documentElement;
    if (root?.attributes?.length > 1) {
      const items = this.sortElements(root.attributes);
      Object.assign(root.attributes, items);
    }
    const shasum = crypto.createHash('sha256');
    shasum.update(doc.toString(), 'utf8');
    return shasum.digest('base64');
  };

  getAlgorithmName = () => 'http://www.w3.org/2001/04/xmlenc#sha256';
}
