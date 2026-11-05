import React from "react";

const sampleCategories = [
  {
    name: "Ayakkabılar",
    description: "Spor, günlük ve özel davetler için ayakkabılar",
    items: [
      {
        name: "Beyaz Deri Sneaker",
        image:
          "https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=400&q=60",
        location: "Üst kattaki depo rafı",
        notes: "Günlük kombinlerin kurtarıcısı."
      },
      {
        name: "Siyah Topuklu",
        image:
          "https://images.unsplash.com/photo-1528702748617-c64d49f918af?auto=format&fit=crop&w=400&q=60",
        location: "Yatak odası dolabı alt çekmece",
        notes: "Özel gecelerde tercih ediliyor."
      }
    ]
  },
  {
    name: "Ceketler",
    description: "Mevsimlik ve şık ceket koleksiyonu",
    items: [
      {
        name: "Oversize Kot Ceket",
        image:
          "https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?auto=format&fit=crop&w=400&q=60",
        location: "Antre askılık",
        notes: "Serin akşamlarda ideal."
      },
      {
        name: "Kamel Trençkot",
        image:
          "https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=400&q=60",
        location: "Vestiyer orta bölüm",
        notes: "Yağmurlu günlerde su geçirmez."
      }
    ]
  },
  {
    name: "Aksesuarlar",
    description: "Takılar ve tamamlayıcı parçalar",
    items: [
      {
        name: "Altın Kalpli Kolye",
        image:
          "https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?auto=format&fit=crop&w=400&q=60",
        location: "Yatak odası sol çekmece",
        notes: "Özel günler için saklanıyor."
      },
      {
        name: "Minimalist Saat",
        image:
          "https://images.unsplash.com/photo-1524594154908-edd325d4e4a0?auto=format&fit=crop&w=400&q=60",
        location: "Çalışma masası üst çekmece",
        notes: "Her kombinle uyumlu."
      }
    ]
  }
];

const combinations = [
  {
    title: "Hafta Sonu Kahvaltısı",
    items: ["Beyaz Deri Sneaker", "Oversize Kot Ceket", "Minimalist Saat"],
    tip: "Kot ceket ve beyaz sneaker ile rahat ama özenli bir görünüm."
  },
  {
    title: "Ofis Toplantısı",
    items: ["Kamel Trençkot", "Siyah Topuklu", "Altın Kalpli Kolye"],
    tip: "Topuklu ayakkabı ve altın aksesuar kombinasyonu şık bir kontrast sağlar."
  },
  {
    title: "Akşam Yemeği",
    items: ["Siyah Topuklu", "Altın Kalpli Kolye", "Minimalist Saat"],
    tip: "Minimal aksesuarlarla zarif bir görünüm yakalayın."
  }
];

const workflowSteps = [
  {
    title: "Kategori Oluştur",
    description:
      "Ayakkabı, ceket, takı gibi istediğin kadar kategori ekle. Her kategori için açıklamalar bırak."
  },
  {
    title: "Ürün Fotoğrafı Çek",
    description:
      "Uygulama içinden hızlıca fotoğraf çek veya galerinden yükle. Konum notlarını eklemeyi unutma."
  },
  {
    title: "Konumunu Kaydet",
    description:
      "Her parçanın nerede olduğunu not al; çekmece, depo veya valiz fark etmeksizin kolayca bul."
  },
  {
    title: "Kombin Önerisi Al",
    description:
      "Dolabındaki parçaları filtrele, ruh haline göre kombin önerileri iste."
  }
];

function SectionTitle({ title, subtitle }) {
  return (
    <div className="text-center max-w-3xl mx-auto mb-12">
      <h2 className="text-3xl md:text-4xl font-bold text-slate-50 mb-4">{title}</h2>
      {subtitle && <p className="text-lg text-slate-300">{subtitle}</p>}
    </div>
  );
}

export default function App() {
  return (
    <div className="min-h-screen bg-slate-950 text-slate-100">
      <header className="relative overflow-hidden bg-gradient-to-br from-indigo-500 via-purple-500 to-slate-900">
        <div className="absolute inset-0 opacity-40 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.6),_transparent_60%)]" />
        <div className="relative mx-auto max-w-6xl px-6 py-20 md:py-28">
          <span className="inline-flex items-center rounded-full bg-white/20 px-4 py-1 text-sm font-medium uppercase tracking-wide">
            Prototip Sürüm
          </span>
          <h1 className="mt-6 text-4xl md:text-6xl font-semibold leading-tight">
            Gardırop Asistanı ile kıyafetlerini kategorize et, konumlarını kaydet ve kombin önerileri al.
          </h1>
          <p className="mt-6 max-w-2xl text-lg md:text-xl text-slate-100/80">
            Bu prototip, uygulamanın çekirdek deneyimini görselleştirir: kategori oluşturma, ürün fotoğraflama, konum notu ekleme ve akıllı kombin önerileri.
          </p>
          <div className="mt-10 flex flex-wrap gap-4">
            <button className="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-slate-900 shadow-lg shadow-slate-900/20 transition hover:-translate-y-0.5 hover:shadow-xl">
              Demo Akışını İncele
            </button>
            <button className="rounded-xl border border-white/60 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
              Tasarım Notlarını Görüntüle
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-6 py-16 space-y-24">
        <section>
          <SectionTitle
            title="Öne Çıkan Özellikler"
            subtitle="MVP aşamasında hedeflenen kullanıcı deneyimi blokları"
          />
          <div className="grid gap-6 md:grid-cols-3">
            {[
              {
                title: "Kişiselleştirilebilir Kategoriler",
                description:
                  "Kullanıcılar kendi kategorilerini oluşturup düzenleyebilir, renk ve ikon seçebilir."
              },
              {
                title: "Hızlı Fotoğraf Yükleme",
                description:
                  "Kamera entegrasyonu sayesinde saniyeler içinde ürün görseli eklenir."
              },
              {
                title: "Konum Notları",
                description:
                  "Her parça için serbest metin alanıyla bulunduğu yeri kaydet."
              },
              {
                title: "Filtreleme ve Arama",
                description:
                  "Kategori, renk veya etikete göre gardırobu tarayarak parçaları bul."
              },
              {
                title: "Kombin Öneri Motoru",
                description:
                  "Favori parçalarından otomatik kombinler oluştur ve kaydet."
              },
              {
                title: "Paylaşılabilir Listeler",
                description:
                  "Yakında: Tatil valizi veya özel gün kombinlerini arkadaşlarınla paylaş."
              }
            ].map((feature) => (
              <div
                key={feature.title}
                className="rounded-2xl border border-white/10 bg-white/5 p-6 shadow-lg shadow-black/10 backdrop-blur"
              >
                <h3 className="text-xl font-semibold text-white">{feature.title}</h3>
                <p className="mt-3 text-sm text-slate-200/80">{feature.description}</p>
              </div>
            ))}
          </div>
        </section>

        <section>
          <SectionTitle
            title="Kategori ve Ürün Önizlemesi"
            subtitle="Örnek veri setiyle prototip ekran düzeni"
          />
          <div className="grid gap-8 md:grid-cols-3">
            {sampleCategories.map((category) => (
              <div
                key={category.name}
                className="flex h-full flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-900/60 shadow-xl"
              >
                <div className="p-6">
                  <h3 className="text-2xl font-semibold text-white">{category.name}</h3>
                  <p className="mt-2 text-sm text-slate-300">{category.description}</p>
                </div>
                <div className="space-y-4 bg-slate-900/40 p-6">
                  {category.items.map((item) => (
                    <article
                      key={item.name}
                      className="flex gap-4 rounded-2xl bg-white/5 p-4 shadow-inner shadow-black/20"
                    >
                      <img
                        src={item.image}
                        alt={item.name}
                        className="h-20 w-20 flex-shrink-0 rounded-xl object-cover"
                      />
                      <div>
                        <h4 className="text-lg font-semibold text-white">{item.name}</h4>
                        <p className="text-xs text-purple-200/80">{item.location}</p>
                        <p className="mt-1 text-sm text-slate-300">{item.notes}</p>
                      </div>
                    </article>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </section>

        <section>
          <SectionTitle
            title="Kombin Önerileri"
            subtitle="Yapay zekâ destekli eşleştirme motorundan örnek sonuçlar"
          />
          <div className="grid gap-6 md:grid-cols-3">
            {combinations.map((combo) => (
              <div
                key={combo.title}
                className="flex h-full flex-col rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-950 p-6 shadow-xl"
              >
                <h3 className="text-2xl font-semibold text-white">{combo.title}</h3>
                <ul className="mt-4 space-y-2 text-sm text-slate-200/90">
                  {combo.items.map((item) => (
                    <li key={item} className="flex items-center gap-2">
                      <span className="inline-flex h-2 w-2 rounded-full bg-violet-400" />
                      {item}
                    </li>
                  ))}
                </ul>
                <p className="mt-6 rounded-2xl bg-white/10 p-3 text-sm text-slate-200/80">{combo.tip}</p>
              </div>
            ))}
          </div>
        </section>

        <section>
          <SectionTitle
            title="Prototip Kullanıcı Akışı"
            subtitle="Kullanıcı testleri için önerilen adımlar"
          />
          <div className="grid gap-8 md:grid-cols-2">
            {workflowSteps.map((step, index) => (
              <div
                key={step.title}
                className="rounded-3xl border border-white/10 bg-slate-900/60 p-6 shadow-lg shadow-black/20"
              >
                <div className="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-500/80 text-lg font-semibold text-white">
                  {index + 1}
                </div>
                <h3 className="text-xl font-semibold text-white">{step.title}</h3>
                <p className="mt-2 text-sm text-slate-300">{step.description}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="rounded-3xl border border-white/10 bg-white/5 p-10 text-center shadow-xl">
          <h2 className="text-3xl font-semibold text-white">Birlikte İnşa Edelim</h2>
          <p className="mt-4 text-sm text-slate-200/80">
            Bu prototip, nihai ürünün temelini oluşturur. Kullanıcı geri bildirimi topladıktan sonra canlı yayına hazırlık, backend entegrasyonu ve mağaza süreçlerine geçebiliriz.
          </p>
          <div className="mt-6 flex flex-wrap justify-center gap-4">
            <button className="rounded-xl bg-indigo-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-400">
              Kullanıcı Testi Planla
            </button>
            <button className="rounded-xl border border-white/40 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
              Özellik Listesini Paylaş
            </button>
          </div>
        </section>
      </main>

      <footer className="border-t border-white/10 bg-slate-950/80 py-8">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 text-center text-xs text-slate-400 md:flex-row md:text-left">
          <p>© {new Date().getFullYear()} Gardırop Asistanı • Prototip Sürüm</p>
          <div className="flex gap-4">
            <a href="mailto:info@gardiropasistani.app" className="hover:text-white">
              İletişim
            </a>
            <a href="https://instagram.com" className="hover:text-white">
              Instagram
            </a>
            <a href="https://www.figma.com" className="hover:text-white">
              Figma Dosyası
            </a>
          </div>
        </div>
      </footer>
    </div>
  );
}
