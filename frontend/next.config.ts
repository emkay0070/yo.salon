import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  allowedDevOrigins: ['127.0.0.1'],
  async rewrites() {
    return [
      {
        source: "/backend/:path*",
        destination: "https://16.171.141.237/:path*",
      },
    ];
  },
};

export default nextConfig;
