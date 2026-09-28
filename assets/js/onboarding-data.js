/**
 * TutorMind onboarding data — universities, Ghana SHS programmes, and the
 * quick-check question bank (3 questions per bank; aptitude has spares).
 * Carried over verbatim from the retired onboarding-bundle.js wizard.
 * Used by onboarding-flow.js.
 */
window.TM_ONBOARDING_DATA = {
  "universities": [
    "University of Ghana (UG) – Legon, Accra",
    "Kwame Nkrumah University of Science and Technology (KNUST) – Kumasi",
    "University of Cape Coast (UCC) – Cape Coast",
    "University of Education, Winneba (UEW) – Winneba",
    "University for Development Studies (UDS) – Tamale",
    "University of Mines and Technology (UMaT) – Tarkwa",
    "University of Health and Allied Sciences (UHAS) – Ho",
    "University of Energy and Natural Resources (UENR) – Sunyani",
    "University of Professional Studies, Accra (UPSA) – Legon, Accra",
    "Ghana Institute of Management and Public Administration (GIMPA) – Legon, Accra",
    "Ashesi University – Berekuso",
    "Central University – Miotso (Tema)",
    "Valley View University (VVU) – Oyibi",
    "Accra Institute of Technology (AIT) – Accra",
    "Academic City University College – Accra"
  ],
  "shsPrograms": {
    "general-science": {
      "name": "General Science",
      "description": "Physics, Chemistry, Biology, Elective Maths",
      "electives": [
        {
          "id": "physics",
          "name": "Physics"
        },
        {
          "id": "chemistry",
          "name": "Chemistry"
        },
        {
          "id": "biology",
          "name": "Biology"
        },
        {
          "id": "elective-maths",
          "name": "Elective Mathematics"
        }
      ]
    },
    "general-arts": {
      "name": "General Arts",
      "description": "Literature, History, Geography, Languages",
      "electives": [
        {
          "id": "literature",
          "name": "Literature in English"
        },
        {
          "id": "history",
          "name": "History"
        },
        {
          "id": "government",
          "name": "Government"
        },
        {
          "id": "french",
          "name": "French"
        }
      ]
    },
    "business": {
      "name": "Business",
      "description": "Accounting, Economics, Business Management",
      "electives": [
        {
          "id": "financial-accounting",
          "name": "Financial Accounting"
        },
        {
          "id": "business-management",
          "name": "Business Management"
        },
        {
          "id": "economics",
          "name": "Economics"
        },
        {
          "id": "elective-maths",
          "name": "Elective Mathematics"
        }
      ]
    },
    "visual-arts": {
      "name": "Visual Arts",
      "description": "Graphics, Sculpture, Painting, Textiles",
      "electives": [
        {
          "id": "graphic-design",
          "name": "Graphic Design"
        },
        {
          "id": "picture-making",
          "name": "Picture Making"
        },
        {
          "id": "general-knowledge",
          "name": "General Knowledge in Art"
        }
      ]
    },
    "home-economics": {
      "name": "Home Economics",
      "description": "Food & Nutrition, Textiles, Management",
      "electives": [
        {
          "id": "food-nutrition",
          "name": "Food & Nutrition"
        },
        {
          "id": "management-living",
          "name": "Management in Living"
        }
      ]
    },
    "technical": {
      "name": "Technical",
      "description": "Building, Engineering, Electronics",
      "electives": [
        {
          "id": "technical-drawing",
          "name": "Technical Drawing"
        },
        {
          "id": "electronics",
          "name": "Electronics"
        }
      ]
    }
  },
  "questionBank": {
    "mathematics": [
      {
        "text": "Solve for x: 2x + 4 = 14",
        "options": [
          "4",
          "5",
          "6",
          "9"
        ],
        "correct": 1
      },
      {
        "text": "What is the slope of the line y = 3x - 7?",
        "options": [
          "7",
          "-7",
          "3",
          "-3"
        ],
        "correct": 2
      },
      {
        "text": "What is 15% of 200?",
        "options": [
          "20",
          "25",
          "30",
          "35"
        ],
        "correct": 2
      }
    ],
    "science": [
      {
        "text": "What is the chemical symbol for water?",
        "options": [
          "WO",
          "H2O",
          "HO2",
          "W2O"
        ],
        "correct": 1
      },
      {
        "text": "How many planets are in our solar system?",
        "options": [
          "7",
          "8",
          "9",
          "10"
        ],
        "correct": 1
      },
      {
        "text": "What force keeps planets in orbit around the sun?",
        "options": [
          "Magnetism",
          "Friction",
          "Gravity",
          "Electricity"
        ],
        "correct": 2
      }
    ],
    "languages": [
      {
        "text": "Which sentence is grammatically correct?",
        "options": [
          "She don't like it.",
          "She doesn't likes it.",
          "She doesn't like it.",
          "She not like it."
        ],
        "correct": 2
      },
      {
        "text": "What is the past tense of 'write'?",
        "options": [
          "writed",
          "wrote",
          "written",
          "writ"
        ],
        "correct": 1
      },
      {
        "text": "Which word is a synonym for 'happy'?",
        "options": [
          "Sad",
          "Angry",
          "Joyful",
          "Tired"
        ],
        "correct": 2
      }
    ],
    "computer-science": [
      {
        "text": "What does 'HTML' stand for?",
        "options": [
          "HyperText Markup Language",
          "High-Tech Machine Logic",
          "HyperText Machine Language",
          "High Transfer Markup Language"
        ],
        "correct": 0
      },
      {
        "text": "Which of these is a programming language?",
        "options": [
          "Excel",
          "Python",
          "Photoshop",
          "Chrome"
        ],
        "correct": 1
      },
      {
        "text": "What is the result of 5 % 2 in most programming languages?",
        "options": [
          "2",
          "2.5",
          "1",
          "0"
        ],
        "correct": 2
      }
    ],
    "social-studies": [
      {
        "text": "What is the capital of France?",
        "options": [
          "Berlin",
          "Madrid",
          "Rome",
          "Paris"
        ],
        "correct": 3
      },
      {
        "text": "Which document declared American independence?",
        "options": [
          "The Constitution",
          "The Magna Carta",
          "The Declaration of Independence",
          "The Bill of Rights"
        ],
        "correct": 2
      },
      {
        "text": "What is an economy based primarily on services called?",
        "options": [
          "Agricultural economy",
          "Industrial economy",
          "Service economy",
          "Barter economy"
        ],
        "correct": 2
      }
    ],
    "business": [
      {
        "text": "What does 'debit' mean in accounting?",
        "options": [
          "Money owed to others",
          "An entry that increases assets or expenses",
          "A reduction in revenue",
          "Money earned from sales"
        ],
        "correct": 1
      },
      {
        "text": "What is the accounting equation?",
        "options": [
          "Revenue = Expenses + Profit",
          "Assets = Liabilities + Equity",
          "Profit = Revenue - Assets",
          "Equity = Assets + Liabilities"
        ],
        "correct": 1
      },
      {
        "text": "What does GDP stand for?",
        "options": [
          "Gross Domestic Product",
          "General Demand Price",
          "Gross Debt Percentage",
          "Government Data Program"
        ],
        "correct": 0
      }
    ],
    "general-science": [
      {
        "text": "What is the powerhouse of the cell?",
        "options": [
          "Nucleus",
          "Ribosome",
          "Mitochondria",
          "Vacuole"
        ],
        "correct": 2
      },
      {
        "text": "What is the atomic number of Carbon?",
        "options": [
          "4",
          "6",
          "8",
          "12"
        ],
        "correct": 1
      },
      {
        "text": "Which law states that force equals mass times acceleration?",
        "options": [
          "Newton's 1st Law",
          "Newton's 2nd Law",
          "Newton's 3rd Law",
          "Hooke's Law"
        ],
        "correct": 1
      }
    ],
    "visual-arts": [
      {
        "text": "Which are the primary colors?",
        "options": [
          "Red, Green, Blue",
          "Red, Yellow, Blue",
          "Orange, Purple, Green",
          "Red, White, Blue"
        ],
        "correct": 1
      },
      {
        "text": "What technique involves applying thick paint to create texture?",
        "options": [
          "Watercolor wash",
          "Impasto",
          "Glazing",
          "Stippling"
        ],
        "correct": 1
      },
      {
        "text": "Who painted the Mona Lisa?",
        "options": [
          "Michelangelo",
          "Raphael",
          "Leonardo da Vinci",
          "Donatello"
        ],
        "correct": 2
      }
    ],
    "home-economics": [
      {
        "text": "Which nutrient provides the most energy per gram?",
        "options": [
          "Protein",
          "Carbohydrates",
          "Fat",
          "Vitamins"
        ],
        "correct": 2
      },
      {
        "text": "What is the safe internal temperature for cooked chicken?",
        "options": [
          "60°C / 140°F",
          "74°C / 165°F",
          "90°C / 194°F",
          "50°C / 122°F"
        ],
        "correct": 1
      },
      {
        "text": "Which stitch is most commonly used to start hand sewing?",
        "options": [
          "Running stitch",
          "Backstitch",
          "Slip stitch",
          "Blanket stitch"
        ],
        "correct": 1
      }
    ],
    "uni-accounting": [
      {
        "text": "Under IFRS, how are financial assets measured by default?",
        "options": [
          "At historical cost",
          "At fair value",
          "At replacement cost",
          "At book value"
        ],
        "correct": 1
      },
      {
        "text": "Which concept states that a business is separate from its owner?",
        "options": [
          "Going concern",
          "Prudence",
          "Business entity",
          "Matching"
        ],
        "correct": 2
      },
      {
        "text": "What does a Statement of Cash Flows NOT include?",
        "options": [
          "Operating activities",
          "Investing activities",
          "Financing activities",
          "Budgeting activities"
        ],
        "correct": 3
      }
    ],
    "uni-finance": [
      {
        "text": "What does NPV stand for?",
        "options": [
          "Net Present Value",
          "Net Profit Volume",
          "Nominal Price Value",
          "Net Price Variance"
        ],
        "correct": 0
      },
      {
        "text": "A bond trading above its face value is said to be trading at a:",
        "options": [
          "Discount",
          "Premium",
          "Par",
          "Deficit"
        ],
        "correct": 1
      },
      {
        "text": "Which measure captures the sensitivity of a bond's price to interest rate changes?",
        "options": [
          "Beta",
          "Duration",
          "Yield",
          "Coupon rate"
        ],
        "correct": 1
      }
    ],
    "uni-economics": [
      {
        "text": "When demand falls as income rises, the good is called:",
        "options": [
          "Normal good",
          "Inferior good",
          "Giffen good",
          "Luxury good"
        ],
        "correct": 1
      },
      {
        "text": "Which market structure has a single seller with no close substitutes?",
        "options": [
          "Oligopoly",
          "Monopolistic competition",
          "Monopoly",
          "Perfect competition"
        ],
        "correct": 2
      },
      {
        "text": "The Phillips Curve describes the relationship between:",
        "options": [
          "Inflation and GDP",
          "Unemployment and inflation",
          "Interest rates and savings",
          "Trade deficit and currency value"
        ],
        "correct": 1
      }
    ],
    "uni-business": [
      {
        "text": "What does a SWOT analysis examine?",
        "options": [
          "Sales, Wages, Output, Targets",
          "Strengths, Weaknesses, Opportunities, Threats",
          "Strategy, Work, Operations, Technology",
          "Systems, Workflow, Objectives, Tasks"
        ],
        "correct": 1
      },
      {
        "text": "Which leadership style involves employees in decision-making?",
        "options": [
          "Autocratic",
          "Laissez-faire",
          "Transactional",
          "Democratic"
        ],
        "correct": 3
      },
      {
        "text": "Porter's Five Forces does NOT include:",
        "options": [
          "Threat of new entrants",
          "Buyer power",
          "Employee satisfaction",
          "Competitive rivalry"
        ],
        "correct": 2
      }
    ],
    "uni-cs": [
      {
        "text": "What is the time complexity of binary search?",
        "options": [
          "O(n)",
          "O(n²)",
          "O(log n)",
          "O(1)"
        ],
        "correct": 2
      },
      {
        "text": "Which data structure uses LIFO (Last In, First Out)?",
        "options": [
          "Queue",
          "Stack",
          "Linked list",
          "Tree"
        ],
        "correct": 1
      },
      {
        "text": "What does SQL stand for?",
        "options": [
          "Structured Query Language",
          "Simple Query Logic",
          "Standard Queue Language",
          "Sequential Query Layer"
        ],
        "correct": 0
      }
    ],
    "uni-engineering": [
      {
        "text": "What is Ohm's Law?",
        "options": [
          "V = IR",
          "P = mv²",
          "F = ma",
          "E = mc²"
        ],
        "correct": 0
      },
      {
        "text": "Which material has the highest electrical conductivity?",
        "options": [
          "Iron",
          "Aluminium",
          "Silver",
          "Copper"
        ],
        "correct": 2
      },
      {
        "text": "What does CAD stand for in engineering?",
        "options": [
          "Computer-Aided Design",
          "Circuit Analysis Diagram",
          "Calculated Axial Displacement",
          "Component Array Database"
        ],
        "correct": 0
      }
    ],
    "uni-medicine": [
      {
        "text": "Which organ produces insulin?",
        "options": [
          "Liver",
          "Kidney",
          "Pancreas",
          "Spleen"
        ],
        "correct": 2
      },
      {
        "text": "What is the normal resting heart rate range for adults?",
        "options": [
          "40–60 bpm",
          "60–100 bpm",
          "100–120 bpm",
          "120–140 bpm"
        ],
        "correct": 1
      },
      {
        "text": "Which blood type is the universal donor?",
        "options": [
          "A+",
          "B+",
          "AB+",
          "O−"
        ],
        "correct": 3
      }
    ],
    "uni-law": [
      {
        "text": "What does 'mens rea' mean in criminal law?",
        "options": [
          "The guilty act",
          "The guilty mind",
          "The victim's intent",
          "The court's finding"
        ],
        "correct": 1
      },
      {
        "text": "Which source of law carries the highest authority in most countries?",
        "options": [
          "Case law",
          "Statute law",
          "The constitution",
          "Customary law"
        ],
        "correct": 2
      },
      {
        "text": "What is the standard of proof in civil cases?",
        "options": [
          "Beyond reasonable doubt",
          "Balance of probabilities",
          "Clear and convincing evidence",
          "Absolute certainty"
        ],
        "correct": 1
      }
    ],
    "uni-psychology": [
      {
        "text": "Who is known as the father of psychoanalysis?",
        "options": [
          "Carl Jung",
          "B.F. Skinner",
          "Sigmund Freud",
          "William James"
        ],
        "correct": 2
      },
      {
        "text": "Classical conditioning was demonstrated using a dog by:",
        "options": [
          "Freud",
          "Pavlov",
          "Skinner",
          "Bandura"
        ],
        "correct": 1
      },
      {
        "text": "Maslow's hierarchy places which need at the top?",
        "options": [
          "Safety",
          "Love and belonging",
          "Esteem",
          "Self-actualization"
        ],
        "correct": 3
      }
    ],
    "uni-biology": [
      {
        "text": "What is the basic unit of heredity?",
        "options": [
          "Cell",
          "Chromosome",
          "Gene",
          "Protein"
        ],
        "correct": 2
      },
      {
        "text": "During which phase of mitosis do chromosomes line up at the cell's equator?",
        "options": [
          "Prophase",
          "Anaphase",
          "Telophase",
          "Metaphase"
        ],
        "correct": 3
      },
      {
        "text": "Which molecule carries genetic information from DNA to the ribosome?",
        "options": [
          "tRNA",
          "mRNA",
          "rRNA",
          "siRNA"
        ],
        "correct": 1
      }
    ],
    "uni-chemistry": [
      {
        "text": "What is the pH of a neutral solution at 25°C?",
        "options": [
          "0",
          "7",
          "14",
          "10"
        ],
        "correct": 1
      },
      {
        "text": "Which type of bond involves the sharing of electrons?",
        "options": [
          "Ionic bond",
          "Hydrogen bond",
          "Covalent bond",
          "Metallic bond"
        ],
        "correct": 2
      },
      {
        "text": "What is the molar mass of water (H₂O)?",
        "options": [
          "16 g/mol",
          "18 g/mol",
          "20 g/mol",
          "22 g/mol"
        ],
        "correct": 1
      }
    ],
    "uni-history": [
      {
        "text": "Which event triggered the start of World War I?",
        "options": [
          "The invasion of Poland",
          "The assassination of Archduke Franz Ferdinand",
          "The sinking of the Lusitania",
          "The bombing of Pearl Harbor"
        ],
        "correct": 1
      },
      {
        "text": "The Cold War was primarily between which two superpowers?",
        "options": [
          "USA and China",
          "UK and USSR",
          "USA and USSR",
          "China and USA"
        ],
        "correct": 2
      },
      {
        "text": "Which African country was never colonized by a European power?",
        "options": [
          "Ghana",
          "Nigeria",
          "Ethiopia",
          "Kenya"
        ],
        "correct": 2
      }
    ],
    "uni-education": [
      {
        "text": "Bloom's Taxonomy categorizes learning into how many levels?",
        "options": [
          "4",
          "5",
          "6",
          "7"
        ],
        "correct": 2
      },
      {
        "text": "Which learning theory is based on observable behavior changes?",
        "options": [
          "Constructivism",
          "Cognitivism",
          "Behaviourism",
          "Humanism"
        ],
        "correct": 2
      },
      {
        "text": "What does IEP stand for in special education?",
        "options": [
          "Individual Education Plan",
          "Integrated Education Program",
          "Instructional Evaluation Process",
          "Inclusive Engagement Practice"
        ],
        "correct": 0
      }
    ],
    "aptitude": [
      {
        "text": "If a train travels 120 km in 2 hours, what is its average speed?",
        "options": [
          "40 km/h",
          "50 km/h",
          "60 km/h",
          "80 km/h"
        ],
        "correct": 2
      },
      {
        "text": "Which number comes next in the series: 2, 4, 8, 16, ___?",
        "options": [
          "24",
          "28",
          "30",
          "32"
        ],
        "correct": 3
      },
      {
        "text": "A is taller than B. B is taller than C. Who is the shortest?",
        "options": [
          "A",
          "B",
          "C",
          "Cannot be determined"
        ],
        "correct": 2
      },
      {
        "text": "What is 30% of 150?",
        "options": [
          "35",
          "40",
          "45",
          "50"
        ],
        "correct": 2
      },
      {
        "text": "If all roses are flowers and some flowers fade quickly, which statement must be true?",
        "options": [
          "All roses fade quickly",
          "Some roses may fade quickly",
          "No roses fade quickly",
          "All flowers are roses"
        ],
        "correct": 1
      }
    ]
  },
  "uniKeywords": [
    {
      "keys": [
        "accounting",
        "financial account",
        "auditing",
        "taxation",
        "bookkeep"
      ],
      "bank": "uni-accounting"
    },
    {
      "keys": [
        "finance",
        "investment",
        "banking",
        "portfolio",
        "asset management",
        "corporate finance"
      ],
      "bank": "uni-finance"
    },
    {
      "keys": [
        "economics",
        "microeconomics",
        "macroeconomics",
        "econometrics"
      ],
      "bank": "uni-economics"
    },
    {
      "keys": [
        "business",
        "management",
        "marketing",
        "entrepreneurship",
        "human resource",
        "supply chain",
        "logistics",
        "mba"
      ],
      "bank": "uni-business"
    },
    {
      "keys": [
        "computer",
        "software",
        "programming",
        "data science",
        "cybersecurity",
        "artificial intelligence",
        "machine learning",
        "it ",
        "information technology",
        "networking"
      ],
      "bank": "uni-cs"
    },
    {
      "keys": [
        "engineering",
        "mechanical",
        "electrical",
        "civil",
        "chemical engineering",
        "aerospace",
        "biomedical"
      ],
      "bank": "uni-engineering"
    },
    {
      "keys": [
        "medicine",
        "medical",
        "nursing",
        "pharmacy",
        "pharmacology",
        "anatomy",
        "physiology",
        "dentistry",
        "public health"
      ],
      "bank": "uni-medicine"
    },
    {
      "keys": [
        "law",
        "legal",
        "jurisprudence",
        "criminology"
      ],
      "bank": "uni-law"
    },
    {
      "keys": [
        "psychology",
        "counselling",
        "psychiatry",
        "neuroscience",
        "behavioral"
      ],
      "bank": "uni-psychology"
    },
    {
      "keys": [
        "biology",
        "microbiology",
        "genetics",
        "ecology",
        "zoology",
        "botany",
        "biochemistry"
      ],
      "bank": "uni-biology"
    },
    {
      "keys": [
        "chemistry",
        "organic chemistry",
        "inorganic",
        "analytical chemistry"
      ],
      "bank": "uni-chemistry"
    },
    {
      "keys": [
        "history",
        "political science",
        "international relations",
        "geography",
        "sociology",
        "anthropology"
      ],
      "bank": "uni-history"
    },
    {
      "keys": [
        "education",
        "pedagogy",
        "curriculum",
        "teaching",
        "early childhood"
      ],
      "bank": "uni-education"
    },
    {
      "keys": [
        "mathematics",
        "statistics",
        "calculus",
        "algebra",
        "actuarial"
      ],
      "bank": "mathematics"
    },
    {
      "keys": [
        "literature",
        "linguistics",
        "english",
        "communication",
        "journalism",
        "french",
        "spanish"
      ],
      "bank": "languages"
    },
    {
      "keys": [
        "physics",
        "astrophysics",
        "thermodynamics",
        "quantum"
      ],
      "bank": "general-science"
    }
  ]
};
