const https = require('https');

function searchImages(query) {
  return new Promise((resolve) => {
    https.get(`https://html.duckduckgo.com/html/?q=${encodeURIComponent(query)}`, {
      headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        const matches = data.match(/images\.unsplash\.com\/photo-[a-zA-Z0-9\-]+/g);
        if (matches) {
          resolve(`https://${matches[0]}?q=80&w=1200&auto=format&fit=crop`);
        } else {
          resolve(null);
        }
      });
    }).on('error', () => resolve(null));
  });
}

async function main() {
  const queries = [
    { name: 'salon_empty_chair.jpg', q: 'site:unsplash.com dark luxury barber chair moody' },
    { name: 'salon_tools_dark.jpg', q: 'site:unsplash.com salon scissors tools dark' },
    { name: 'salon_reception.jpg', q: 'site:unsplash.com modern dark reception desk salon' },
    { name: 'salon_moody_corner.jpg', q: 'site:unsplash.com dark luxury salon interior' }
  ];

  for (const item of queries) {
    console.log(`Searching for ${item.name}...`);
    const url = await searchImages(item.q);
    if (url) {
      console.log(`Found: ${url}`);
    } else {
      console.log(`Not found for ${item.name}`);
    }
  }
}

main();
