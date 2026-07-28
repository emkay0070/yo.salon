/** @type {import('next').NextConfig} */
const nextConfig = {
  allowedDevOrigins: ['192.168.10.100', '192.168.10.101'],
  async rewrites() {
    return [
      {
        source: "/backend/:path*",
        destination: "http://16.171.141.237/:path*",
      },
    ];
  },
};

module.exports = nextConfig;
