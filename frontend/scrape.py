import urllib.request
import re
import sys

def search_images(query):
    url = f"https://html.duckduckgo.com/html/?q={query.replace(' ', '+')}"
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
    try:
        html = urllib.request.urlopen(req).read().decode('utf-8')
        # Look for unsplash image URLs
        urls = re.findall(r'https://images\.unsplash\.com/photo-[a-zA-Z0-9\-]+', html)
        if urls:
            return urls[0] + "?q=80&w=1200&auto=format&fit=crop"
    except Exception as e:
        print(f"Error: {e}")
    return None

queries = [
    "site:unsplash.com dark luxury salon interior",
    "site:unsplash.com barber chair dark moody",
    "site:unsplash.com salon scissors tools dark",
    "site:unsplash.com modern dark reception desk"
]

for q in queries:
    print(f"Searching for {q}...")
    url = search_images(q)
    if url:
        print(f"Found: {url}")
    else:
        print("Not found.")
