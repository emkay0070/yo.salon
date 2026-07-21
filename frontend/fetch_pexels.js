const https = require('https');
const fs = require('fs');
const path = require('path');

function searchPexels(query) {
  return new Promise((resolve) => {
    https.get(`https://www.pexels.com/search/${encodeURIComponent(query)}/`, {
      headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        const matches = data.match(/https:\/\/images\.pexels\.com\/photos\/\d+\/pexels-photo-\d+\.jpeg/g);
        if (matches) {
          resolve(matches[0] + "?auto=compress&cs=tinysrgb&w=1200");
        } else {
          resolve(null);
        }
      });
    }).on('error', () => resolve(null));
  });
}

function downloadImage(url, filename) {
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(filename);
    https.get(url, (response) => {
      response.pipe(file);
      file.on('finish', () => {
        file.close();
        resolve();
      });
    }).on('error', (err) => {
      fs.unlink(filename, () => {});
      reject(err);
    });
  });
}

async function main() {
  const queries = [
    { name: 'salon_empty_chair.jpg', q: 'dark barbershop chair' },
    { name: 'salon_tools_dark.jpg', q: 'salon scissors dark' },
    { name: 'salon_reception.jpg', q: 'modern dark reception desk' },
    { name: 'salon_moody_corner.jpg', q: 'dark luxury salon interior' }
  ];

  const imagesDir = path.join(__dirname, 'public', 'images');
  if (!fs.existsSync(imagesDir)) {
    fs.mkdirSync(imagesDir, { recursive: true });
  }

  for (const item of queries) {
    console.log(`Searching Pexels for ${item.q}...`);
    const url = await searchPexels(item.q);
    if (url) {
      console.log(`Found: ${url}`);
      const filePath = path.join(imagesDir, item.name);
      await downloadImage(url, filePath);
      console.log(`Downloaded to ${filePath}`);
    } else {
      console.log(`Not found for ${item.name}`);
    }
  }
}

main();
