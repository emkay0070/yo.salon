import type { NextConfig } from "next";

const isDev = process.env.NODE_ENV === "development";

const nextConfig: NextConfig = {
  allowedDevOrigins: [
    'localhost',
    '127.0.0.1',
    '192.168.10.100',
    '192.168.10.101',
    // Public HTTPS tunnel hosts used for local Flutterwave sandbox testing.
    'named-gene-opposed-sports.trycloudflare.com',
    '*.ngrok-free.app',
    '*.ngrok.io',
    '*.trycloudflare.com',
    '*.loca.lt',
  ],
  async rewrites() {
    const rewrites = [
      {
        source: "/backend/:path*",
        destination: "https://16.171.141.237/:path*",
      },
    ];

    // Development-only: proxy the local API through Next.js so the browser
    // talks to a single (tunnel) origin and avoids mixed-content/CORS issues.
    if (isDev) {
      rewrites.push({
        source: "/api/:path*",
        destination: "http://localhost:8000/api/:path*",
      });
    }

    return rewrites;
  },
};

export default nextConfig;
