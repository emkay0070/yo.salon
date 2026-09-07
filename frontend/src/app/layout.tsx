import type { Metadata } from "next";
import { Outfit, Plus_Jakarta_Sans, Playfair_Display } from "next/font/google";
import "./globals.css";
import Providers from "@/components/Providers";

const fontOutfit = Outfit({
  subsets: ["latin"],
  variable: "--font-outfit",
  display: "swap",
});

const fontJakarta = Plus_Jakarta_Sans({
  subsets: ["latin"],
  variable: "--font-jakarta",
  display: "swap",
});

const fontPlayfair = Playfair_Display({
  subsets: ["latin"],
  variable: "--font-playfair",
  display: "swap",
});

export const metadata: Metadata = {
  title: "Yo Salon - Transform Your Salon Into an Experience",
  description: "AI-powered immersive digital experiences for salons. Transform your website into an experience that customers remember.",
  icons: {
    icon: '/icon.svg',
    shortcut: '/icon.svg',
    apple: '/icon.svg',
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      suppressHydrationWarning
      className={`h-full antialiased ${fontOutfit.variable} ${fontJakarta.variable} ${fontPlayfair.variable}`}
    >
      <body className="min-h-full flex flex-col" suppressHydrationWarning>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
