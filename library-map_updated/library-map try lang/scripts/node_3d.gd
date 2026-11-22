extends Node3D

# =====================================================================
# LIBRARY MAP
# Click a shelf -> the camera flies to it, the genre name and its book
# list show up. Keys: A / D rotate, W top-down view, S back to normal,
# 4 overview (clears the selection).
#
# TRACKING: the "track" button flies to the top-down view and draws a red
# route that grows from the ENTRANCE along the aisles to the selected shelf;
# the arrow shows up at the end of it, pointing at the shelf.
# If the route starts at the wrong door, change ENTRANCE (and nothing else).
#
# TO RENAME A GENRE:  change the text in _build_shelves() AND the same
#                     text as the key in BOOKS (they must match).
# TO ADD BOOKS:       add lines to that genre's list in BOOKS.
# =====================================================================

const ROTATE_SPEED := 60.0   # degrees per second while A / D is held
const CAM_TIME := 1.0        # seconds every camera move takes

# ---------- tracking route ----------
const ENTRANCE := Vector2(1.4, 1.8)   # x, z just inside the door (by the front desk)
const ROUTE_WIDTH := 0.05             # same thickness as the arrow shaft
const ROUTE_THICK := 0.02
const ROUTE_SPEED := 2.5              # metres per second the route grows
const ARROW_HALF_LEN := 1.28          # half the arrow's length (8.84 * its 0.145 scale)
const GRID := 0.1                     # aisle points are snapped to this grid

# ---------- nodes ----------
@onready var camera_3d: Camera3D = $Camera3D
@onready var top_down_camera: Camera3D = $top_down_camera
@onready var section_1_cam: Camera3D = $section1_cam
@onready var section_2_cam: Camera3D = $section2_cam   # low bench
@onready var section_3_cam: Camera3D = $section3_cam   # left wall (middle)
@onready var section_4_cam: Camera3D = $section4_cam   # right wall
@onready var section_5_cam: Camera3D = $section5_cam   # back wall
@onready var section_6_cam: Camera3D = $section6_cam   # left wall (front)
@onready var section_7_cam: Camera3D = $section7_cam   # left wall (back)

@onready var label: Label = $Camera3D/Label
@onready var genre: Label = $Camera3D/genre
@onready var genre_bg: Sprite2D = $Camera3D/genre_bg
@onready var genre_selected: Sprite2D = $Camera3D/genre_selected
@onready var panel_bg: Sprite2D = $Camera3D/Sprite2D
@onready var hide_button: Button = $Camera3D/hide
@onready var show_button: Button = $Camera3D/show

@onready var arrow: Node3D = $arrow
@onready var sec_1_highlight: Sprite3D = $sec1_highlight

# ---------- book lists: "Title\nYear·Author" ----------
const BOOKS := {
	"PHILOSOPHY": [
		"Stoicism 101\n2025·Erick Cloward",
		"The Republic\nc. 375 BC·Plato",
		"Meditations\nc. 180 AD·Marcus Aurelius",
		"Beyond Good and Evil\n1886·Friedrich Nietzsche",
		"Being and Nothingness\n1943·Jean-Paul Sartre",
	],
	"PSYCHOLOGY": [
		"Thinking, Fast and Slow\n2011·Daniel Kahneman",
		"Man's Search for Meaning\n1946·Viktor Frankl",
		"The Body Keeps the Score\n2014·Bessel van der Kolk",
		"Man and His Symbols\n1964·Carl Jung",
		"Drive\n2009·Daniel H. Pink",
	],
	"EDUCATION": [
		"Pedagogy of the Oppressed\n1968·Paulo Freire",
		"Educated\n2018·Tara Westover",
		"Teaching to Transgress\n1994·bell hooks",
		"Experience and Education\n1938·John Dewey",
		"Mindset\n2006·Carol S. Dweck",
	],
	"SCIENCE": [
		"Astrophysics for People in a Hurry\n2017·Neil deGrasse Tyson",
		"A Short History of Nearly Everything\n2003·Bill Bryson",
		"Cosmos\n1980·Carl Sagan",
	],
	"ENGLISH": [
		"To Kill a Mockingbird\n1960·Harper Lee",
		"Pride and Prejudice\n1813·Jane Austen",
		"The Great Gatsby\n1925·F. Scott Fitzgerald",
		"1984\n1949·George Orwell",
		"The Catcher in the Rye\n1951·J.D. Salinger",
	],
	"LAW": [
		"The Republic\nc. 375 BC·Plato",
		"The Common Law\n1881·Oliver Wendell Holmes Jr.",
		"On Liberty\n1859·John Stuart Mill",
		"The Rule of Law\n2010·Tom Bingham",
		"Introduction to the Study of the Law of the Constitution\n1885·A.V. Dicey",
	],

	# ----- left wall -----
	"ROMANCE": [
		"Outlander\n1991·Diana Gabaldon",
		"The Notebook\n1996·Nicholas Sparks",
		"Me Before You\n2012·Jojo Moyes",
		"The Hating Game\n2016·Sally Thorne",
		"Beach Read\n2020·Emily Henry",
	],
	"MYSTERY & THRILLER": [
		"The Hound of the Baskervilles\n1902·Arthur Conan Doyle",
		"And Then There Were None\n1939·Agatha Christie",
		"The Girl with the Dragon Tattoo\n2005·Stieg Larsson",
		"The Da Vinci Code\n2003·Dan Brown",
		"Gone Girl\n2012·Gillian Flynn",
	],
	"HORROR": [
		"Frankenstein\n1818·Mary Shelley",
		"Dracula\n1897·Bram Stoker",
		"The Haunting of Hill House\n1959·Shirley Jackson",
		"The Shining\n1977·Stephen King",
		"Pet Sematary\n1983·Stephen King",
	],
	"SCIENCE FICTION": [
		"Foundation\n1951·Isaac Asimov",
		"Dune\n1965·Frank Herbert",
		"The Left Hand of Darkness\n1969·Ursula K. Le Guin",
		"Neuromancer\n1984·William Gibson",
		"The Martian\n2011·Andy Weir",
	],
	"FANTASY": [
		"The Hobbit\n1937·J.R.R. Tolkien",
		"A Wizard of Earthsea\n1968·Ursula K. Le Guin",
		"Harry Potter and the Philosopher's Stone\n1997·J.K. Rowling",
		"A Game of Thrones\n1996·George R.R. Martin",
		"The Name of the Wind\n2007·Patrick Rothfuss",
	],
	"NATURE & ENVIRONMENT": [
		"Walden\n1854·Henry David Thoreau",
		"A Sand County Almanac\n1949·Aldo Leopold",
		"Silent Spring\n1962·Rachel Carson",
		"Braiding Sweetgrass\n2013·Robin Wall Kimmerer",
		"The Hidden Life of Trees\n2015·Peter Wohlleben",
	],
	"GEOGRAPHY & TRAVEL": [
		"Under the Tuscan Sun\n1996·Frances Mayes",
		"Into the Wild\n1996·Jon Krakauer",
		"Eat, Pray, Love\n2006·Elizabeth Gilbert",
		"The Geography of Bliss\n2008·Eric Weiner",
		"Prisoners of Geography\n2015·Tim Marshall",
	],
	"HISTORY": [
		"The Guns of August\n1962·Barbara W. Tuchman",
		"A People's History of the United States\n1980·Howard Zinn",
		"Guns, Germs, and Steel\n1997·Jared Diamond",
		"Sapiens\n2011·Yuval Noah Harari",
		"SPQR\n2015·Mary Beard",
	],
	"COOKING & FOOD": [
		"The Joy of Cooking\n1931·Irma S. Rombauer",
		"Mastering the Art of French Cooking\n1961·Julia Child et al.",
		"Kitchen Confidential\n2000·Anthony Bourdain",
		"The Omnivore's Dilemma\n2006·Michael Pollan",
		"Salt, Fat, Acid, Heat\n2017·Samin Nosrat",
	],
	"GARDENING & AGRICULTURE": [
		"The One-Straw Revolution\n1975·Masanobu Fukuoka",
		"Square Foot Gardening\n1981·Mel Bartholomew",
		"Teaming with Microbes\n2006·Jeff Lowenfels & Wayne Lewis",
	],
	"BIOGRAPHY & MEMOIR": [
		"The Diary of a Young Girl\n1947·Anne Frank",
		"I Know Why the Caged Bird Sings\n1969·Maya Angelou",
		"Long Walk to Freedom\n1994·Nelson Mandela",
		"Steve Jobs\n2011·Walter Isaacson",
		"Becoming\n2018·Michelle Obama",
	],
	"HEALTH & WELLNESS": [
		"Spark\n2008·John J. Ratey",
		"The Blue Zones\n2008·Dan Buettner",
		"How Not to Die\n2015·Michael Greger",
		"Why We Sleep\n2017·Matthew Walker",
		"The Body\n2019·Bill Bryson",
	],
	"ARTS & MUSIC": [
		"The Story of Art\n1950·E.H. Gombrich",
		"Ways of Seeing\n1972·John Berger",
		"This Is Your Brain on Music\n2006·Daniel J. Levitin",
		"Just Kids\n2010·Patti Smith",
		"Steal Like an Artist\n2012·Austin Kleon",
	],

	# ----- right wall -----
	"POETRY & DRAMA": [
		"Hamlet\nc. 1600·William Shakespeare",
		"Leaves of Grass\n1855·Walt Whitman",
		"The Waste Land\n1922·T.S. Eliot",
		"Death of a Salesman\n1949·Arthur Miller",
		"Milk and Honey\n2014·Rupi Kaur",
	],
	"CHILDREN'S BOOKS": [
		"Goodnight Moon\n1947·Margaret Wise Brown",
		"Charlotte's Web\n1952·E.B. White",
		"Where the Wild Things Are\n1963·Maurice Sendak",
		"The Very Hungry Caterpillar\n1969·Eric Carle",
		"Matilda\n1988·Roald Dahl",
	],
	"RELIGION & THEOLOGY": [
		"The Varieties of Religious Experience\n1902·William James",
		"Mere Christianity\n1952·C.S. Lewis",
		"A History of God\n1993·Karen Armstrong",
		"The Case for Christ\n1998·Lee Strobel",
	],
	"YOUNG ADULT": [
		"The Outsiders\n1967·S.E. Hinton",
		"The Hunger Games\n2008·Suzanne Collins",
		"Divergent\n2011·Veronica Roth",
		"The Fault in Our Stars\n2012·John Green",
		"The Hate U Give\n2017·Angie Thomas",
	],
	"REFERENCE & ENCYCLOPEDIAS": [
		"Encyclopaedia Britannica\n1768·Various authors",
		"Roget's Thesaurus\n1852·Peter Mark Roget",
		"Merriam-Webster's Collegiate Dictionary\n1898·Merriam-Webster",
		"The Chicago Manual of Style\n1906·University of Chicago Press",
		"Guinness World Records\n1955·Guinness",
	],
	"TECHNOLOGY & COMPUTING": [
		"Code\n1999·Charles Petzold",
		"The Pragmatic Programmer\n1999·Andrew Hunt & David Thomas",
		"Clean Code\n2008·Robert C. Martin",
		"The Innovators\n2014·Walter Isaacson",
		"Superintelligence\n2014·Nick Bostrom",
	],
	"NEW ARRIVALS": [],   # the big empty shelf: add books here as they come in

	# ----- back wall -----
	"SELF-HELP": [
		"How to Win Friends and Influence People\n1936·Dale Carnegie",
		"The 7 Habits of Highly Effective People\n1989·Stephen R. Covey",
		"Deep Work\n2016·Cal Newport",
		"Atomic Habits\n2018·James Clear",
	],
	"BUSINESS & ECONOMICS": [
		"The Wealth of Nations\n1776·Adam Smith",
		"Good to Great\n2001·Jim Collins",
		"Freakonomics\n2005·Steven D. Levitt & Stephen J. Dubner",
		"The Lean Startup\n2011·Eric Ries",
		"Capital in the Twenty-First Century\n2013·Thomas Piketty",
	],
	"MATHEMATICS": [
		"Flatland\n1884·Edwin A. Abbott",
		"Godel, Escher, Bach\n1979·Douglas Hofstadter",
		"Fermat's Enigma\n1997·Simon Singh",
		"The Joy of x\n2012·Steven Strogatz",
		"How Not to Be Wrong\n2014·Jordan Ellenberg",
	],
	"SOCIOLOGY": [
		"The Protestant Ethic and the Spirit of Capitalism\n1905·Max Weber",
		"The Presentation of Self in Everyday Life\n1956·Erving Goffman",
		"The Sociological Imagination\n1959·C. Wright Mills",
		"Bowling Alone\n2000·Robert D. Putnam",
		"Evicted\n2016·Matthew Desmond",
	],
	"POLITICAL SCIENCE": [
		"The Prince\n1532·Niccolo Machiavelli",
		"Leviathan\n1651·Thomas Hobbes",
		"The Social Contract\n1762·Jean-Jacques Rousseau",
		"Democracy in America\n1835·Alexis de Tocqueville",
		"The End of History and the Last Man\n1992·Francis Fukuyama",
	],
	"ANTHROPOLOGY": [
		"Argonauts of the Western Pacific\n1922·Bronislaw Malinowski",
		"Coming of Age in Samoa\n1928·Margaret Mead",
		"Tristes Tropiques\n1955·Claude Levi-Strauss",
		"The Interpretation of Cultures\n1973·Clifford Geertz",
		"Debt: The First 5,000 Years\n2011·David Graeber",
	],

	# ----- low bench -----
	"COMICS & GRAPHIC NOVELS": [
		"Maus\n1980·Art Spiegelman",
		"Watchmen\n1986·Alan Moore & Dave Gibbons",
		"Persepolis\n2000·Marjane Satrapi",
		"Fun Home\n2006·Alison Bechdel",
		"Saga\n2012·Brian K. Vaughan & Fiona Staples",
	],
	"HUMOR": [
		"Catch-22\n1961·Joseph Heller",
		"The Hitchhiker's Guide to the Galaxy\n1979·Douglas Adams",
		"Good Omens\n1990·Terry Pratchett & Neil Gaiman",
		"Me Talk Pretty One Day\n2000·David Sedaris",
		"Bossypants\n2011·Tina Fey",
	],
	"ADVENTURE": [
		"Robinson Crusoe\n1719·Daniel Defoe",
		"The Count of Monte Cristo\n1844·Alexandre Dumas",
		"Around the World in Eighty Days\n1872·Jules Verne",
		"Treasure Island\n1883·Robert Louis Stevenson",
		"The Call of the Wild\n1903·Jack London",
	],
	"SHORT STORIES": [
		"Dubliners\n1914·James Joyce",
		"The Lottery and Other Stories\n1949·Shirley Jackson",
		"Nine Stories\n1953·J.D. Salinger",
		"Cathedral\n1983·Raymond Carver",
		"Interpreter of Maladies\n1999·Jhumpa Lahiri",
	],
	"CLASSICS": [
		"Don Quixote\n1605·Miguel de Cervantes",
		"Jane Eyre\n1847·Charlotte Bronte",
		"Moby-Dick\n1851·Herman Melville",
		"Crime and Punishment\n1866·Fyodor Dostoevsky",
		"War and Peace\n1869·Leo Tolstoy",
	],
	"SPORTS & RECREATION": [
		"Friday Night Lights\n1990·H.G. Bissinger",
		"Moneyball\n2003·Michael Lewis",
		"Born to Run\n2009·Christopher McDougall",
		"Open\n2009·Andre Agassi",
		"The Boys in the Boat\n2013·Daniel James Brown",
	],
	"FOLKLORE & MYTHOLOGY": [
		"Grimms' Fairy Tales\n1812·Brothers Grimm",
		"The Golden Bough\n1890·James George Frazer",
		"Mythology\n1942·Edith Hamilton",
		"The Hero with a Thousand Faces\n1949·Joseph Campbell",
		"Norse Mythology\n2017·Neil Gaiman",
	],
	"TRUE CRIME": [
		"In Cold Blood\n1966·Truman Capote",
		"Helter Skelter\n1974·Vincent Bugliosi",
		"The Stranger Beside Me\n1980·Ann Rule",
		"Midnight in the Garden of Good and Evil\n1994·John Berendt",
		"I'll Be Gone in the Dark\n2018·Michelle McNamara",
	],
	"ENGINEERING": [
		"Structures\n1978·J.E. Gordon",
		"The Soul of a New Machine\n1981·Tracy Kidder",
		"To Engineer Is Human\n1985·Henry Petroski",
		"The Design of Everyday Things\n1988·Don Norman",
		"Skunk Works\n1994·Ben R. Rich & Leo Janos",
	],
	"LANGUAGES & LINGUISTICS": [
		"The Story of English\n1986·Robert McCrum et al.",
		"The Language Instinct\n1994·Steven Pinker",
		"Fluent Forever\n2014·Gabriel Wyner",
		"Word by Word\n2017·Kory Stamper",
		"Because Internet\n2019·Gretchen McCulloch",
	],
}

# ---------- state ----------
# genre name -> { area, marker, books, cam, highlight, arrow_point }
var shelves := {}
var all_cams: Array = []

var currently_tracking := ""
var cam_tween: Tween
var is_camera_moving := false   # click lock while a camera move is running

var tracking_on := false        # true once "track" was pressed (until overview)
var route_root: Node3D          # holds the route pieces while one is shown
var route_tween: Tween

# aisle graph (built once, first time a route is needed)
var graph_pos: Array[Vector2] = []
var graph_adj: Array = []       # per point: the indices of its neighbours
var graph_ids := {}             # Vector2i grid key -> index


# =====================================================================
# SETUP
# =====================================================================

func _ready() -> void:
	all_cams = [camera_3d, top_down_camera, section_1_cam, section_2_cam,
			section_3_cam, section_4_cam, section_5_cam, section_6_cam, section_7_cam]
	_build_shelves()

	for shelf_name in shelves:
		var area: Area3D = shelves[shelf_name]["area"]

		# remove old editor connections so nothing fires twice
		for sig in [area.input_event, area.mouse_entered, area.mouse_exited]:
			for c in sig.get_connections():
				sig.disconnect(c["callable"])

		area.input_event.connect(_on_shelf_input_event.bind(shelf_name))
		area.mouse_entered.connect(_on_shelf_hover.bind(shelf_name, true))
		area.mouse_exited.connect(_on_shelf_hover.bind(shelf_name, false))


func _build_shelves() -> void:
	# every shelf: genre name, area, hover marker, section camera, highlight, arrow point
	# (each genre has its own highlight, like every other shelf)
	_add_shelf("PHILOSOPHY", $Philosophy, $Philosophy/philo_marker, section_1_cam, $Philosophy/highlight, $Philosophy/arrow_point)
	_add_shelf("PSYCHOLOGY", $Psychology, $Psychology/psych_marker, section_1_cam, $Psychology/highlight, $Psychology/arrow_point)
	_add_shelf("EDUCATION", $Education, $Education/educ_marker, section_1_cam, $Education/highlight, $Education/arrow_point)
	_add_shelf("SCIENCE", $Science, $Science/sci_marker, section_1_cam, $Science/highlight, $Science/arrow_point)
	_add_shelf("ENGLISH", $English, $English/eng_marker, section_1_cam, $English/highlight, $English/arrow_point)
	_add_shelf("LAW", $Law, $Law/law_marker, section_1_cam, $Law/highlight, $Law/arrow_point)

	# left wall (front camera, middle camera, back camera)
	_add_shelf("ROMANCE", $Romance, $Romance/romance_marker, section_6_cam, $Romance/highlight, $Romance/arrow_point)
	_add_shelf("MYSTERY & THRILLER", $Mystery_Thriller, $Mystery_Thriller/mystery_thriller_marker, section_6_cam, $Mystery_Thriller/highlight, $Mystery_Thriller/arrow_point)
	_add_shelf("HORROR", $Horror, $Horror/horror_marker, section_6_cam, $Horror/highlight, $Horror/arrow_point)
	_add_shelf("SCIENCE FICTION", $Science_Fiction, $Science_Fiction/science_fiction_marker, section_6_cam, $Science_Fiction/highlight, $Science_Fiction/arrow_point)
	_add_shelf("FANTASY", $Fantasy, $Fantasy/fantasy_marker, section_6_cam, $Fantasy/highlight, $Fantasy/arrow_point)
	_add_shelf("NATURE & ENVIRONMENT", $Nature_Environment, $Nature_Environment/nature_environment_marker, section_6_cam, $Nature_Environment/highlight, $Nature_Environment/arrow_point)
	_add_shelf("GEOGRAPHY & TRAVEL", $Geography_Travel, $Geography_Travel/geography_travel_marker, section_3_cam, $Geography_Travel/highlight, $Geography_Travel/arrow_point)
	_add_shelf("HISTORY", $History, $History/history_marker, section_3_cam, $History/highlight, $History/arrow_point)
	_add_shelf("COOKING & FOOD", $Cooking_Food, $Cooking_Food/cooking_food_marker, section_3_cam, $Cooking_Food/highlight, $Cooking_Food/arrow_point)
	_add_shelf("GARDENING & AGRICULTURE", $Gardening_Agriculture, $Gardening_Agriculture/gardening_agriculture_marker, section_3_cam, $Gardening_Agriculture/highlight, $Gardening_Agriculture/arrow_point)
	_add_shelf("BIOGRAPHY & MEMOIR", $Biography_Memoir, $Biography_Memoir/biography_memoir_marker, section_3_cam, $Biography_Memoir/highlight, $Biography_Memoir/arrow_point)
	_add_shelf("HEALTH & WELLNESS", $Health_Wellness, $Health_Wellness/health_wellness_marker, section_7_cam, $Health_Wellness/highlight, $Health_Wellness/arrow_point)
	_add_shelf("ARTS & MUSIC", $Arts_Music, $Arts_Music/arts_music_marker, section_7_cam, $Arts_Music/highlight, $Arts_Music/arrow_point)

	# right wall
	_add_shelf("POETRY & DRAMA", $Poetry_Drama, $Poetry_Drama/poetry_drama_marker, section_4_cam, $Poetry_Drama/highlight, $Poetry_Drama/arrow_point)
	_add_shelf("CHILDREN'S BOOKS", $Childrens_Books, $Childrens_Books/childrens_books_marker, section_4_cam, $Childrens_Books/highlight, $Childrens_Books/arrow_point)
	_add_shelf("RELIGION & THEOLOGY", $Religion_Theology, $Religion_Theology/religion_theology_marker, section_4_cam, $Religion_Theology/highlight, $Religion_Theology/arrow_point)
	_add_shelf("YOUNG ADULT", $Young_Adult, $Young_Adult/young_adult_marker, section_4_cam, $Young_Adult/highlight, $Young_Adult/arrow_point)
	_add_shelf("REFERENCE & ENCYCLOPEDIAS", $Reference_Encyclopedias, $Reference_Encyclopedias/reference_encyclopedias_marker, section_4_cam, $Reference_Encyclopedias/highlight, $Reference_Encyclopedias/arrow_point)
	_add_shelf("TECHNOLOGY & COMPUTING", $Technology_Computing, $Technology_Computing/technology_computing_marker, section_4_cam, $Technology_Computing/highlight, $Technology_Computing/arrow_point)
	_add_shelf("NEW ARRIVALS", $New_Arrivals, $New_Arrivals/new_arrivals_marker, section_4_cam, $New_Arrivals/highlight, $New_Arrivals/arrow_point) # whole empty unit, every shelf in it is clickable

	# back wall
	_add_shelf("SELF-HELP", $Self_Help, $Self_Help/self_help_marker, section_5_cam, $Self_Help/highlight, $Self_Help/arrow_point)
	_add_shelf("BUSINESS & ECONOMICS", $Business_Economics, $Business_Economics/business_economics_marker, section_5_cam, $Business_Economics/highlight, $Business_Economics/arrow_point)
	_add_shelf("MATHEMATICS", $Mathematics, $Mathematics/mathematics_marker, section_5_cam, $Mathematics/highlight, $Mathematics/arrow_point)
	_add_shelf("SOCIOLOGY", $Sociology, $Sociology/sociology_marker, section_5_cam, $Sociology/highlight, $Sociology/arrow_point)
	_add_shelf("POLITICAL SCIENCE", $Political_Science, $Political_Science/political_science_marker, section_5_cam, $Political_Science/highlight, $Political_Science/arrow_point)
	_add_shelf("ANTHROPOLOGY", $Anthropology, $Anthropology/anthropology_marker, section_5_cam, $Anthropology/highlight, $Anthropology/arrow_point)

	# low bench
	_add_shelf("COMICS & GRAPHIC NOVELS", $Comics_Graphic_Novels, $Comics_Graphic_Novels/comics_graphic_novels_marker, section_2_cam, $Comics_Graphic_Novels/highlight, $Comics_Graphic_Novels/arrow_point)
	_add_shelf("HUMOR", $Humor, $Humor/humor_marker, section_2_cam, $Humor/highlight, $Humor/arrow_point)
	_add_shelf("ADVENTURE", $Adventure, $Adventure/adventure_marker, section_2_cam, $Adventure/highlight, $Adventure/arrow_point)
	_add_shelf("SHORT STORIES", $Short_Stories, $Short_Stories/short_stories_marker, section_2_cam, $Short_Stories/highlight, $Short_Stories/arrow_point)
	_add_shelf("CLASSICS", $Classics, $Classics/classics_marker, section_2_cam, $Classics/highlight, $Classics/arrow_point)
	_add_shelf("SPORTS & RECREATION", $Sports_Recreation, $Sports_Recreation/sports_recreation_marker, section_2_cam, $Sports_Recreation/highlight, $Sports_Recreation/arrow_point)
	_add_shelf("FOLKLORE & MYTHOLOGY", $Folklore_Mythology, $Folklore_Mythology/folklore_mythology_marker, section_2_cam, $Folklore_Mythology/highlight, $Folklore_Mythology/arrow_point)
	_add_shelf("TRUE CRIME", $True_Crime, $True_Crime/true_crime_marker, section_2_cam, $True_Crime/highlight, $True_Crime/arrow_point)
	_add_shelf("ENGINEERING", $Engineering, $Engineering/engineering_marker, section_2_cam, $Engineering/highlight, $Engineering/arrow_point)
	_add_shelf("LANGUAGES & LINGUISTICS", $Languages_Linguistics, $Languages_Linguistics/languages_linguistics_marker, section_2_cam, $Languages_Linguistics/highlight, $Languages_Linguistics/arrow_point)


func _add_shelf(genre_name: String, area: Area3D, marker: Node3D, cam: Camera3D,
		highlight: Node3D, arrow_point: Node3D) -> void:
	if shelves.has(genre_name):
		push_warning("Duplicate genre name: " + genre_name)
	shelves[genre_name] = {
		"area": area,
		"marker": marker,
		"books": BOOKS.get(genre_name, []),
		"cam": cam,
		"highlight": highlight,
		"arrow_point": arrow_point,
	}


# =====================================================================
# CAMERA
# =====================================================================

func stop_camera_tween() -> void:
	if cam_tween and cam_tween.is_running():
		cam_tween.kill()


# Smoothly fly `cam` from wherever the view is right now to its home spot.
# on_done (optional) runs when the move finishes.
func _fly_to(cam: Camera3D, on_done: Callable = Callable()) -> void:
	is_camera_moving = true
	stop_camera_tween()

	var target_pos := cam.global_position
	cam.global_position = get_viewport().get_camera_3d().global_position
	cam.make_current()

	cam_tween = create_tween()
	cam_tween.set_trans(Tween.TRANS_CUBIC)
	cam_tween.set_ease(Tween.EASE_IN_OUT)
	cam_tween.tween_property(cam, "global_position", target_pos, CAM_TIME)
	cam_tween.finished.connect(func():
		is_camera_moving = false
		if on_done.is_valid():
			on_done.call()
	)


func _process(delta: float) -> void:
	# A / D rotate every camera together (same speed at any frame rate)
	var dir := Input.get_axis("left", "right")
	if dir != 0.0:
		for cam in all_cams:
			cam.rotation_degrees.y += dir * ROTATE_SPEED * delta


func _unhandled_input(event: InputEvent) -> void:
	if is_camera_moving:
		return

	if event.is_action_pressed("top_down"):
		_fly_to(top_down_camera)

	elif event.is_action_pressed("bottom_up"):
		_fly_to(camera_3d)

	elif event.is_action_pressed("4"):
		# overview: back to the main camera and clear whatever is selected
		_fly_to(camera_3d)
		_clear_selection()


# =====================================================================
# SELECTION / TRACKING
# =====================================================================

func _clear_selection() -> void:
	currently_tracking = ""
	tracking_on = false
	arrow.visible = false
	_clear_route()
	hide_highlights()

	genre.text = ""
	label.text = ""
	genre_selected.visible = false
	genre_bg.visible = true


func tracking() -> void:
	if currently_tracking == "":
		return
	var shelf: Dictionary = shelves[currently_tracking]
	tracking_on = true

	# arrow goes to this shelf's "arrow_point" (a Marker3D inside each shelf),
	# but stays hidden until the route from the entrance has reached it
	var point: Node3D = shelf["arrow_point"]
	arrow.global_position = point.global_position
	arrow.global_rotation = point.global_rotation
	arrow.visible = false

	hide_highlights()
	shelf["highlight"].visible = true

	_draw_route(point)


func hide_highlights() -> void:
	for shelf_name in shelves:
		shelves[shelf_name]["highlight"].visible = false


func _on_shelf_input_event(_camera: Node, event: InputEvent, _pos: Vector3,
		_normal: Vector3, _shape_idx: int, shelf_name: String) -> void:
	if event.is_action_pressed("select") and not is_camera_moving:
		select_shelf(shelf_name)


func select_shelf(shelf_name: String) -> void:
	var shelf: Dictionary = shelves[shelf_name]
	var shelf_cam: Camera3D = shelf["cam"]

	# only fly if we're not already on this shelf's section camera
	if not shelf_cam.current:
		_fly_to(shelf_cam)

	genre.text = shelf_name
	label.text = "\n\n".join(PackedStringArray(shelf["books"]))

	currently_tracking = shelf_name

	# tracking already on? move the route to the newly clicked shelf
	if tracking_on:
		tracking()

	genre_selected.visible = true
	genre_bg.visible = false


func _on_shelf_hover(shelf_name: String, is_hovering: bool) -> void:
	shelves[shelf_name]["marker"].visible = is_hovering


# =====================================================================
# TRACKING ROUTE  (entrance -> aisles -> shelf)
# =====================================================================

# Centre-lines of the walkable aisles: [from_x, from_z, to_x, to_z].
# Everything is axis-aligned, so routes only ever turn at right angles.
func _aisles() -> Array:
	return [
		[ENTRANCE.x, ENTRANCE.y, -0.6, ENTRANCE.y],  # front corridor from the door
		[-0.6, 2.3, -0.6, -8.0],     # long aisle between table columns 2 and 3
		[-0.6, -1.2, -3.4, -1.2],    # behind the bench, across to the left side
		[-2.0, -1.2, -2.0, -8.0],    # aisle between table columns 1 and 2
		[-3.4, -1.2, -3.4, -8.0],    # aisle along the left wall shelves
		[-3.4, -4.2, -0.6, -4.2],    # cross aisle between the two table rows
	]


func _graph_node(p: Vector2) -> int:
	var key := Vector2i(roundi(p.x / GRID), roundi(p.y / GRID))
	if graph_ids.has(key):
		return graph_ids[key]
	graph_pos.append(Vector2(key.x * GRID, key.y * GRID))
	graph_adj.append([])
	graph_ids[key] = graph_pos.size() - 1
	return graph_pos.size() - 1


func _build_graph() -> void:
	graph_pos.clear()
	graph_adj.clear()
	graph_ids.clear()
	for a in _aisles():
		var p0 := Vector2(a[0], a[1])
		var p1 := Vector2(a[2], a[3])
		var steps := maxi(1, roundi(p0.distance_to(p1) / GRID))
		var prev := _graph_node(p0)
		for i in range(1, steps + 1):
			var cur := _graph_node(p0.lerp(p1, float(i) / steps))
			if cur != prev:
				graph_adj[prev].append(cur)
				graph_adj[cur].append(prev)
			prev = cur


# aisle points lying under the arrow's own body: the route must not run
# along those (it has to arrive at the tail from behind instead)
func _blocked_nodes(tail: Vector2, head_dir: Vector2) -> Array[bool]:
	var blocked: Array[bool] = []
	blocked.resize(graph_pos.size())
	blocked.fill(false)
	for i in graph_pos.size():
		var rel := graph_pos[i] - tail
		var along := rel.dot(head_dir)
		var across := absf(rel.cross(head_dir))
		if along > 0.15 and along < ARROW_HALF_LEN * 2.0 + 0.2 and across < 0.3:
			blocked[i] = true
	return blocked


func _nearest_node(p: Vector2, blocked: Array[bool]) -> int:
	var best := 0
	var best_d := INF
	for i in graph_pos.size():
		if blocked[i]:
			continue
		var d := graph_pos[i].distance_to(p)
		if d < best_d:
			best_d = d
			best = i
	return best


# shortest way along the aisles (plain Dijkstra, the graph is tiny)
func _shortest_path(start: int, goal: int, blocked: Array[bool]) -> Array[int]:
	var n := graph_pos.size()
	var dist: Array[float] = []
	var prev: Array[int] = []
	var done: Array[bool] = []
	dist.resize(n)
	prev.resize(n)
	done.resize(n)
	dist.fill(INF)
	prev.fill(-1)
	done.fill(false)
	dist[start] = 0.0
	for _step in n:
		var u := -1
		var best := INF
		for k in n:
			if not done[k] and dist[k] < best:
				best = dist[k]
				u = k
		if u == -1 or u == goal:
			break
		done[u] = true
		for v in graph_adj[u]:
			if blocked[v]:
				continue
			var nd: float = dist[u] + graph_pos[u].distance_to(graph_pos[v])
			if nd < dist[v]:
				dist[v] = nd
				prev[v] = u
	var out: Array[int] = []
	if dist[goal] == INF:
		return out
	var c := goal
	while c != -1:
		out.push_front(c)
		c = prev[c]
	return out


# corner points (x, z) from the entrance to the arrow's tail end
func _route_points(tail: Vector2, head_dir: Vector2) -> Array[Vector2]:
	if graph_pos.is_empty():
		_build_graph()

	var blocked := _blocked_nodes(tail, head_dir)
	var ids := _shortest_path(_nearest_node(ENTRANCE, blocked), _nearest_node(tail, blocked), blocked)
	var pts: Array[Vector2] = [ENTRANCE]
	for i in ids:
		pts.append(graph_pos[i])

	# last bend: leave the aisle sideways, then run along the arrow's own axis
	var last: Vector2 = pts[pts.size() - 1]
	var corner := Vector2(last.x, tail.y)
	if absf(head_dir.x) < 0.5:
		corner = Vector2(tail.x, last.y)
	pts.append(corner)
	# if the tail is "behind" the corner the arrow body already covers it
	if (tail - corner).dot(head_dir) >= 0.0:
		pts.append(tail)

	# tidy: drop repeats, merge straight runs, cut small overshoots
	var out: Array[Vector2] = []
	for p in pts:
		if out.size() > 0 and out[out.size() - 1].distance_to(p) < 0.06:
			continue
		while out.size() >= 2:
			var a: Vector2 = out[out.size() - 2]
			var b: Vector2 = out[out.size() - 1]
			var straight := absf((b - a).cross(p - b)) < 0.0005
			if straight and ((b - a).dot(p - b) > 0.0 or a.distance_to(p) < a.distance_to(b)):
				out.pop_back()
			else:
				break
		out.append(p)
	return out


func _clear_route() -> void:
	if route_tween and route_tween.is_running():
		route_tween.kill()
	route_tween = null
	if route_root:
		route_root.queue_free()
		route_root = null


func _draw_route(point: Node3D) -> void:
	_clear_route()

	# the arrow's head is its local -Z end; the route has to reach the other end
	var head_dir3 := -point.global_transform.basis.z
	var head_dir := Vector2(head_dir3.x, head_dir3.z).normalized()
	var tail3 := point.global_position - head_dir3.normalized() * ARROW_HALF_LEN
	var pts := _route_points(Vector2(tail3.x, tail3.z), head_dir)

	var y := point.global_position.y
	var mat := StandardMaterial3D.new()
	mat.albedo_color = Color(1, 0, 0.012)           # same red as the arrow
	mat.shading_mode = BaseMaterial3D.SHADING_MODE_UNSHADED

	route_root = Node3D.new()
	add_child(route_root)
	route_tween = create_tween()

	for i in range(1, pts.size()):
		var a := Vector3(pts[i - 1].x, y, pts[i - 1].y)
		var b := Vector3(pts[i].x, y, pts[i].y)
		var seg_len := a.distance_to(b)

		# pivot sits at the start of the segment and looks towards its end;
		# scaling it along Z makes the strip "grow" out of the start
		var pivot := Node3D.new()
		route_root.add_child(pivot)
		pivot.position = a
		pivot.rotation.y = atan2(-(b.x - a.x), -(b.z - a.z))
		pivot.scale = Vector3(1.0, 1.0, 0.001)

		var mesh := BoxMesh.new()
		mesh.size = Vector3(ROUTE_WIDTH, ROUTE_THICK, seg_len + ROUTE_WIDTH)  # a bit longer so corners close up
		var strip := MeshInstance3D.new()
		strip.mesh = mesh
		strip.material_override = mat
		strip.position = Vector3(0.0, 0.0, -seg_len / 2.0)
		pivot.add_child(strip)

		route_tween.tween_property(pivot, "scale:z", 1.0, seg_len / ROUTE_SPEED)

	# route reached the shelf: bring in the arrow
	route_tween.tween_callback(func(): arrow.visible = true)


# =====================================================================
# BUTTONS (connected in the scene)
# =====================================================================

# "track" button: top-down view, then show the arrow on the selected shelf
func _on_button_pressed() -> void:
	if is_camera_moving:
		return
	_fly_to(top_down_camera, tracking)


func _on_button_2_pressed() -> void:   # hide panel
	hide_button.visible = false
	label.visible = false
	panel_bg.visible = false
	show_button.visible = true


func _on_hide_2_pressed() -> void:     # show panel
	hide_button.visible = true
	label.visible = true
	panel_bg.visible = true
	show_button.visible = false
