import React from "react";

export default function App() {
  return (
    <div className="bg-white text-gray-900 font-sans">
      <section className="min-h-screen flex flex-col justify-center items-center text-center p-6 bg-gradient-to-r from-gray-100 to-gray-300">
        <h1 className="text-4xl md:text-6xl font-bold mb-4">Dijitalmarkethink ile Markanızı Güçlendirin</h1>
        <p className="text-xl md:text-2xl mb-6">Meta reklamcılığında uzmanlaşmış, dönüşüm odaklı bir dijital ajansız.</p>
        <button className="px-6 py-3 bg-blue-600 text-white rounded-2xl shadow-lg hover:bg-blue-700 transition">Bizimle İletişime Geç</button>
      </section>

      <section className="p-10 bg-white">
        <h2 className="text-3xl font-bold mb-6 text-center">Hakkımızda</h2>
        <div className="grid md:grid-cols-3 gap-8 text-center">
          <div>
            <h3 className="text-xl font-semibold mb-2">Geçmişimiz</h3>
            <p>2019'dan bu yana yüzlerce markanın dijital büyüme yolculuğuna rehberlik ettik.</p>
          </div>
          <div>
            <h3 className="text-xl font-semibold mb-2">Misyonumuz</h3>
            <p>Veriye dayalı stratejilerle, markaların dijitalde sürdürülebilir bir başarıya ulaşmasını sağlamak.</p>
          </div>
          <div>
            <h3 className="text-xl font-semibold mb-2">Vizyonumuz</h3>
            <p>Türkiye’nin en güvenilir performans ajansı olmak ve global pazarda ses getiren markalar yaratmak.</p>
          </div>
        </div>
      </section>

      <section className="p-10 bg-gray-50">
        <h2 className="text-3xl font-bold mb-6 text-center">Hizmetlerimiz</h2>
        <ul className="grid md:grid-cols-2 gap-4 text-center text-lg">
          <li>Meta Reklam Yönetimi</li>
          <li>AI Destekli Hedef Kitle Belirleme</li>
          <li>Görsel Tasarım & Kreatif Üretim</li>
          <li>Otomasyon ve Performans Raporlama</li>
          <li>Shopify & E-Ticaret Danışmanlığı</li>
        </ul>
      </section>

      <section className="p-10 bg-white">
        <h2 className="text-3xl font-bold mb-6 text-center">Referanslarımız</h2>
        <div className="grid md:grid-cols-3 gap-6 text-center">
          <div className="p-4 border rounded-xl shadow-sm">
            <h3 className="font-semibold">bblokonline.com</h3>
            <p>Erkek giyim markası için 3 ayda ROAS 7.4'e ulaştık.</p>
          </div>
          <div className="p-4 border rounded-xl shadow-sm">
            <h3 className="font-semibold">Nuakids</h3>
            <p>%42 daha düşük tıklama maliyeti sağladık.</p>
          </div>
          <div className="p-4 border rounded-xl shadow-sm">
            <h3 className="font-semibold">Akvadük Zeytin</h3>
            <p>500+ dönüşüm ile başarı hikayesi yazdık.</p>
          </div>
        </div>
      </section>

      <section className="p-10 bg-gray-100 text-center">
        <h2 className="text-3xl font-bold mb-4">İletişime Geç</h2>
        <p className="mb-2">WhatsApp: <a href="https://wa.me/905XXXXXXXXX" className="text-blue-600 underline">Hemen Yaz</a></p>
        <p className="mb-2">E-Posta: <a href="mailto:info@dijitalmarkethink.com" className="text-blue-600 underline">info@dijitalmarkethink.com</a></p>
        <p>Instagram: <a href="https://instagram.com/dijitalmarkethink" className="text-blue-600 underline">@dijitalmarkethink</a></p>
      </section>
    </div>
  );
}
